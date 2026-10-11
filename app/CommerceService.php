<?php

namespace App;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\WalletEntry;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommerceService
{
    public function wallet(User $actor, User $owner, int $amount, string $kind, string $key, string $description, ?int $branchId = null, ?string $subjectType = null, ?int $subjectId = null): WalletEntry
    {
        return DB::transaction(function () use ($actor, $owner, $amount, $kind, $key, $description, $branchId, $subjectType, $subjectId): WalletEntry {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $existing = WalletEntry::where('request_key', $key)->first();
            if ($existing) {
                if ($existing->user_id !== $owner->id || $existing->actor_id !== $actor->id || $existing->amount !== $amount || $existing->kind !== $kind || $existing->subject_type !== $subjectType || $existing->subject_id !== $subjectId) {
                    throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho giao dịch khác.']);
                }

                return $existing;
            }
            if (! $owner->active || $owner->wallet_balance + $amount < 0 || $owner->wallet_balance + $amount > 2147483647) {
                throw ValidationException::withMessages(['amount' => 'Số dư ví không đủ hoặc vượt giới hạn.']);
            }
            $owner->wallet_balance += $amount;
            $owner->save();
            $entry = WalletEntry::create(['user_id' => $owner->id, 'actor_id' => $actor->id, 'branch_id' => $branchId, 'request_key' => $key, 'amount' => $amount, 'balance_after' => $owner->wallet_balance, 'kind' => $kind, 'description' => $description, 'subject_type' => $subjectType, 'subject_id' => $subjectId]);
            $owner->notify(new HubNotification(['type' => 'wallet.'.$kind, 'title' => 'Ví HUB được cập nhật', 'body' => $description, 'wallet_entry_id' => $entry->id]));
            app(BookingService::class)->audit($actor, 'wallet.'.$kind, $entry->id, ['user_id' => $owner->id, 'amount' => $amount, 'balance_after' => $owner->wallet_balance], 'wallet');

            return $entry;
        }, 5);
    }

    public function payment(User $actor, User $owner, string $type, int $id, int $amount, string $method, ?int $branchId = null, string $kind = 'payment'): void
    {
        if ($amount === 0) {
            return;
        }
        DB::table('commerce_payments')->insert(['user_id' => $owner->id, 'actor_id' => $actor->id, 'subject_type' => $type, 'subject_id' => $id, 'amount' => $amount, 'method' => $method, 'branch_id' => $branchId, 'kind' => $kind, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function discounts(?User $user, int $courtAmount, int $branchId, ?string $code = null): array
    {
        $membership = $user ? Membership::with('plan')->where('user_id', $user->id)->where('starts_at', '<=', now())->where('expires_at', '>', now())->get()->sortByDesc(fn ($m) => $m->plan->discount_percent)->first() : null;
        $discount = $membership ? (int) floor($courtAmount * $membership->plan->discount_percent / 100) : 0;
        $coupon = null;
        if ($code) {
            $coupon = Coupon::where('code', strtoupper(trim($code)))->first();
            if (! $coupon || ! $coupon->active || now()->lt($coupon->starts_at) || now()->gte($coupon->expires_at) || ($coupon->branch_id && $coupon->branch_id !== $branchId) || $courtAmount < $coupon->minimum_amount || Booking::where('coupon_id', $coupon->id)->where('status', '!=', 'cancelled')->count() >= $coupon->usage_limit || ($user && Booking::where('coupon_id', $coupon->id)->where('user_id', $user->id)->where('status', '!=', 'cancelled')->exists())) {
                throw ValidationException::withMessages(['coupon_code' => 'Mã ưu đãi không khả dụng hoặc đã được sử dụng.']);
            }
            $value = $coupon->kind === 'percent' ? (int) floor(($courtAmount - $discount) * $coupon->value / 100) : $coupon->value;
            $discount += min($courtAmount - $discount, $value, $coupon->max_discount ?? $courtAmount);
        }

        return ['discount_amount' => $discount, 'coupon_id' => $coupon?->id, 'membership' => $membership?->plan->name ?? 'Basic'];
    }

    public function payBooking(User $actor, Booking $booking, string $key, bool $depositOnly = false): Payment
    {
        return DB::transaction(function () use ($actor, $booking, $key, $depositOnly): Payment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($actor->id !== $booking->user_id) {
                app(BookingService::class)->assertManage($actor, $booking->court->branch_id, 'payments.write');
            }
            $existing = Payment::where('request_key', $key)->first();
            if ($existing) {
                abort_unless($existing->booking_id === $booking->id && $existing->method === 'wallet' && (($existing->notes === 'Đặt cọc qua ví') === $depositOnly), 422);

                return $existing;
            }
            if (! in_array($booking->status, ['confirmed', 'checked_in'], true) || $booking->total_amount <= $booking->paid_amount) {
                throw ValidationException::withMessages(['booking' => 'Lịch không còn khoản cần thanh toán.']);
            }
            $amount = ($depositOnly ? $booking->deposit_amount : $booking->total_amount) - $booking->paid_amount;
            abort_if($amount <= 0, 422, 'Đã thanh toán đủ phần đặt cọc.');
            $this->wallet($actor, $booking->user, -$amount, 'booking', $key, 'Thanh toán '.$booking->reference, $booking->court->branch_id, 'booking', $booking->id);
            $payment = Payment::create(['request_key' => $key, 'booking_id' => $booking->id, 'received_by' => $actor->id, 'kind' => 'payment', 'method' => 'wallet', 'amount' => $amount, 'notes' => $depositOnly ? 'Đặt cọc qua ví' : null]);
            $booking->paid_amount += $amount;
            $booking->save();
            app(BookingService::class)->audit($actor, 'booking.payment', $booking->id, ['amount' => $amount, 'method' => 'wallet']);

            return $payment;
        }, 5);
    }

    public function refundWalletBooking(User $actor, Booking $booking): void
    {
        $netWallet = $booking->payments()->where('method', 'wallet')->get()->sum(fn ($p) => $p->kind === 'refund' ? -$p->amount : $p->amount);
        if ($booking->paid_amount === 0 || $netWallet !== $booking->paid_amount) {
            throw ValidationException::withMessages(['status' => 'Lịch dùng phương thức khác cần quầy xử lý hoàn tiền.']);
        }
        $key = (string) Str::uuid();
        $this->wallet($actor, $booking->user, $booking->paid_amount, 'refund', $key, 'Hoàn tiền '.$booking->reference, $booking->court->branch_id, 'booking', $booking->id);
        Payment::create(['request_key' => $key, 'booking_id' => $booking->id, 'received_by' => $actor->id, 'kind' => 'refund', 'method' => 'wallet', 'amount' => $booking->paid_amount]);
        $booking->paid_amount = 0;
    }

    public function membership(User $actor, User $owner, MembershipPlan $plan, array $data): Membership
    {
        return DB::transaction(function () use ($actor, $owner, $plan, $data): Membership {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $existing = Membership::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $owner->id && $existing->membership_plan_id === $plan->id && $existing->method === $data['method'], 422);

                return $existing->load('plan');
            }
            abort_unless($plan->active && $owner->active, 422);
            if ($data['method'] !== 'wallet') {
                app(BookingService::class)->assertManage($actor, (int) $data['branch_id']);
            } else {
                abort_unless($owner->id === $actor->id || $actor->role !== 'customer', 403);
            }
            $previous = Membership::where('user_id', $owner->id)->where('membership_plan_id', $plan->id)->where('expires_at', '>', now())->latest('expires_at')->first();
            $member = Membership::create(['user_id' => $owner->id, 'membership_plan_id' => $plan->id, 'request_key' => $data['request_key'], 'amount' => $plan->price, 'method' => $data['method'], 'starts_at' => now(), 'expires_at' => ($previous?->expires_at ?? now())->addDays($plan->duration_days)]);
            if ($data['method'] === 'wallet' && $plan->price > 0) {
                $this->wallet($actor, $owner, -$plan->price, 'membership', $data['request_key'], 'Gói '.$plan->name, $data['branch_id'] ?? null, 'membership', $member->id);
            }
            $this->payment($actor, $owner, 'membership', $member->id, $plan->price, $data['method'], $data['branch_id'] ?? null);
            $owner->notify(new HubNotification(['type' => 'membership.activated', 'title' => 'Đã kích hoạt hội viên', 'body' => 'Gói '.$plan->name.' có hiệu lực đến '.$member->expires_at->format('d/m/Y').'.']));
            app(BookingService::class)->audit($actor, 'membership.activated', $member->id, ['user_id' => $owner->id, 'plan' => $plan->name, 'amount' => $plan->price], 'membership');

            return $member->load('plan');
        }, 5);
    }

    public function sale(User $actor, array $data): Sale
    {
        foreach (['user_id', 'booking_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        return DB::transaction(function () use ($actor, $data): Sale {
            app(BookingService::class)->assertManage($actor, (int) $data['branch_id']);
            $booking = isset($data['booking_id']) ? Booking::whereKey($data['booking_id'])->lockForUpdate()->firstOrFail() : null;
            abort_unless(($data['method'] === 'booking') === (bool) $booking, 422, 'Gộp hóa đơn cần chọn lịch sân; thanh toán riêng không chọn lịch.');
            if ($booking) {
                abort_unless(app(AccessService::class)->allows($actor, 'bookings.write'), 403);
                abort_unless($booking->court->branch_id === (int) $data['branch_id'], 403);
                $data['user_id'] ??= $booking->user_id;
                abort_unless((int) $data['user_id'] === $booking->user_id, 422, 'Khách mua phải là chủ lịch sân.');
            }
            $ids = collect($data['items'])->pluck('product_id')->sort()->all();
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = Sale::where('request_key', $data['request_key'])->with('items')->first();
            if ($existing) {
                $wanted = collect($data['items'])->mapWithKeys(fn ($item) => [(int) $item['product_id'] => (int) $item['quantity']])->sortKeys()->all();
                $actual = $existing->items->mapWithKeys(fn ($item) => [$item->product_id => $item->quantity])->sortKeys()->all();
                abort_unless($existing->branch_id === (int) $data['branch_id'] && $existing->clerk_id === $actor->id && $existing->method === $data['method'] && $existing->booking_id === ($data['booking_id'] ?? null) && $existing->user_id === ($data['user_id'] ?? null) && $wanted === $actual, 422);

                return $existing;
            }
            $items = [];
            if ($booking) {
                abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Chỉ thêm hàng cho lịch đã xác nhận hoặc đang chơi.');
            }
            $total = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->active || $product->branch_id !== (int) $data['branch_id'] || $product->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Sản phẩm không khả dụng, sai chi nhánh hoặc không đủ tồn kho.']);
                }
                $lineAmount = $product->price * $item['quantity'];
                $total += $lineAmount;
                $items[] = ['product_id' => $product->id, 'name' => $product->name, 'quantity' => $item['quantity'], 'unit_price' => $product->price, 'amount' => $lineAmount];
            }
            abort_if($total > 2147483647, 422, 'Tổng đơn vượt giới hạn.');
            $owner = isset($data['user_id']) ? User::where('role', 'customer')->where('active', true)->findOrFail($data['user_id']) : $actor;
            $sale = Sale::create(['request_key' => $data['request_key'], 'reference' => 'POS-'.strtoupper(Str::random(10)), 'branch_id' => $data['branch_id'], 'clerk_id' => $actor->id, 'user_id' => $data['user_id'] ?? null, 'total_amount' => $total, 'method' => $data['method'], 'booking_id' => $data['booking_id'] ?? null]);
            if ($data['method'] === 'wallet') {
                abort_unless(isset($data['user_id']), 422, 'Chọn khách hàng để thanh toán ví.');
                $this->wallet($actor, $owner, -$total, 'pos', $data['request_key'], 'Đơn '.$sale->reference, $sale->branch_id, 'sale', $sale->id);
            }
            foreach ($items as $item) {
                $products[$item['product_id']]->decrement('stock', $item['quantity']);
                $sale->items()->create($item);
            }
            if ($booking) {
                abort_if($booking->total_amount + $total > 2147483647, 422);
                $booking->product_amount += $total;
                $booking->total_amount += $total;
                $booking->save();
            } else {
                $this->payment($actor, $owner, 'sale', $sale->id, $total, $data['method'], $sale->branch_id);
            }
            app(BookingService::class)->audit($actor, 'sale.created', $sale->id, ['reference' => $sale->reference, 'total' => $total], 'sale');

            if (isset($data['user_id']) && ! $booking) {
                app(LoyaltyService::class)->award($owner, $total, 'sale-'.$sale->id, 'Mua hàng '.$sale->reference);
            }

            return $sale->load(['items', 'branch']);
        }, 5);
    }
}
