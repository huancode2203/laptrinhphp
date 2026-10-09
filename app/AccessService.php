<?php

namespace App;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessService
{
    public const PERMISSIONS = ['bookings.read', 'bookings.write', 'payments.write', 'pos.read', 'pos.write', 'courts.read', 'courts.write', 'customers.read', 'customers.write', 'reports.read', 'catalog.write', 'events.write', 'support.write'];

    public function branches(User $user): array
    {
        if ($user->role === 'admin') {
            return DB::table('branches')->pluck('id')->all();
        }

        return array_values(array_unique(array_filter([$user->branch_id, ...DB::table('user_branches')->where('user_id', $user->id)->pluck('branch_id')->all()])));
    }

    public function manages(User $user, int $branch): bool
    {
        return $user->role === 'admin' || (in_array($user->role, ['manager', 'staff', 'cashier'], true) && in_array($branch, $this->branches($user), true));
    }

    public function scope(Builder $query, Request $r, string $column = 'branch_id'): Builder
    {
        if ($r->user()->role === 'admin') {
            return $query->when($r->filled('branch_id'), fn ($q) => $q->where($column, $r->integer('branch_id')));
        }
        $allowed = $this->branches($r->user());
        if ($r->filled('branch_id')) {
            abort_unless(in_array($r->integer('branch_id'), $allowed, true), 403);
            $allowed = [$r->integer('branch_id')];
        }

        return $query->whereIn($column, $allowed);
    }

    public function allows(User $user, string $permission): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        if ($user->role === 'customer') {
            return false;
        }
        $override = DB::table('role_permissions')->where('role', $user->role)->where('permission', $permission)->first();
        if ($override) {
            return (bool) $override->allowed;
        }
        if ($user->role === 'manager') {
            return true;
        }

        return in_array($permission, ['bookings.read', 'bookings.write', 'payments.write', 'pos.read', 'pos.write', 'courts.read', 'customers.read', 'customers.write', 'reports.read', 'support.write'], true);
    }

    public function conversations(User $user): Builder
    {
        $bookings = Booking::where('user_id', $user->id);
        if ($user->role === 'admin') {
            $bookings = Booking::query();
        } elseif (in_array($user->role, ['manager', 'staff', 'cashier'], true)) {
            $bookings->orWhereHas('court', fn ($q) => $q->whereIn('branch_id', $this->branches($user)));
        }

        return Conversation::whereHas('members', fn ($q) => $q->where('users.id', $user->id))->where(fn ($q) => $q->whereNull('booking_id')->orWhereIn('booking_id', $bookings->select('id')));
    }

    public function permission(Request $r): ?string
    {
        $path = $r->path();
        $write = ! in_array($r->method(), ['GET', 'HEAD'], true);
        if (str_starts_with($path, 'api/v1/admin/')) {
            return null;
        }
        if (str_contains($path, 'reports')) {
            return 'reports.read';
        }
        if (str_contains($path, 'payments') || str_contains($path, 'topup') || str_contains($path, 'refund')) {
            return 'payments.write';
        }
        if (str_contains($path, 'sales')) {
            return 'pos.'.($write ? 'write' : 'read');
        }
        if (str_contains($path, 'courts') || str_contains($path, 'maintenances') || str_contains($path, 'pricing') || str_contains($path, '/policy') || str_contains($path, 'reviews')) {
            return 'courts.'.($write ? 'write' : 'read');
        }
        if (str_contains($path, 'customers')) {
            return 'customers.'.($write ? 'write' : 'read');
        }
        if (str_contains($path, 'products') || str_contains($path, 'services')) {
            return 'catalog.write';
        }
        if (str_contains($path, 'events') || str_contains($path, 'matches') || str_contains($path, 'coaches')) {
            return $write ? 'events.write' : 'bookings.read';
        }
        if (str_contains($path, 'support')) {
            return 'support.write';
        }
        if (str_contains($path, 'calendar') || str_contains($path, 'bookings') || str_contains($path, 'checkin')) {
            return 'bookings.'.($write ? 'write' : 'read');
        }

        return 'bookings.read';
    }
}
