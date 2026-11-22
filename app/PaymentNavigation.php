<?php

namespace App;

use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

class PaymentNavigation
{
    public function capture(string $role, ?string $target): array
    {
        return $this->allowed($role, $target) ? ['return_role' => $role, 'return_to' => $target] : [];
    }

    public function destination(Request $request, PaymentAttempt $attempt): string
    {
        $role = $request->user()->role;
        $extra = json_decode(base64_decode($attempt->extra_data), true) ?: [];
        if (($extra['return_role'] ?? null) === $role && $this->allowed($role, $extra['return_to'] ?? null)) {
            return $extra['return_to'];
        }

        return match ($role) {
            'admin' => '#admin/payments',
            'customer' => '#account/'.($attempt->booking_id ? 'bookings/'.$attempt->booking_id : ($attempt->purpose === 'membership' ? 'membership' : 'wallet')),
            default => in_array($attempt->purpose, ['wallet', 'membership'], true) ? '#manage/customers' : '#manage/bookings',
        };
    }

    private function allowed(string $role, ?string $target): bool
    {
        if (! $target || strlen($target) > 200) {
            return false;
        }
        $pages = match ($role) {
            'customer' => 'account/(?:wallet|membership|bookings(?:/(?:pending|[0-9]+))?)',
            'admin' => 'admin/(?:payments|dashboard|reports|users)',
            default => 'manage/(?:customers|bookings|calendar|matrix|pos|finance|dashboard)',
        };

        return (bool) preg_match('~^\#'.$pages.'$~D', $target);
    }
}
