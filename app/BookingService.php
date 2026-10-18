<?php

namespace App;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtHold;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\HubNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function window(string $startsAt, string $endsAt): array
    {
        $zone = config('app.timezone');
        $start = CarbonImmutable::parse($startsAt, $zone)->setTimezone($zone);
        $end = CarbonImmutable::parse($endsAt, $zone)->setTimezone($zone);
        $minutes = (int) $start->diffInMinutes($end, false);
        if ($start->lt(now()->startOfMinute())) {
            throw ValidationException::withMessages(['starts_at' => 'Khung giờ này đã qua. Vui lòng chọn giờ bắt đầu từ thời điểm hiện tại trở đi.']);
        }
        if (! $start->isSameDay($end)) {
            throw ValidationException::withMessages(['ends_at' => 'Giờ bắt đầu và giờ kết thúc phải nằm trong cùng một ngày.']);
        }
        if ($minutes < 30 || $minutes > 360) {
            throw ValidationException::withMessages(['ends_at' => 'Mỗi buổi chơi kéo dài từ 30 phút đến 6 giờ. Vui lòng điều chỉnh giờ kết thúc.']);
        }
        if ($start->minute % 30 !== 0 || $end->minute % 30 !== 0 || $start->second !== 0 || $end->second !== 0 || $start->microsecond !== 0 || $end->microsecond !== 0) {
            throw ValidationException::withMessages(['starts_at' => 'Chọn giờ tròn hoặc giờ rưỡi, ví dụ 13:00 hoặc 13:30.']);
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    public function quote(Court $court, CarbonImmutable $start, CarbonImmutable $end, array $selectedServices = [], ?User $actor = null, ?string $couponCode = null, bool $fixed = false): array
    {
        $courtAmount = 0;
        $rules = DB::table('pricing_rules')->where('branch_id', $court->branch_id)->where('active', true)->where(fn ($q) => $q->whereNull('court_id')->orWhere('court_id', $court->id))->orderByDesc('priority')->orderByDesc('id')->get();
        for ($slot = $start; $slot->lt($end); $slot = $slot->addMinutes(30)) {
            $minute = $slot->hour * 60 + $slot->minute;
            $rule = $rules->first(fn ($v) => ($v->date === null || $v->date === $slot->toDateString()) && ($v->weekday === null || (int) $v->weekday === $slot->dayOfWeek) && $minute >= $v->from_minute && $minute < $v->to_minute);
            $rate = $rule?->hourly_rate ?? ($slot->isWeekend() ? $court->weekend_rate : ($minute >= $court->branch->peak_minute ? $court->peak_rate : $court->hourly_rate));
            $courtAmount += (int) round($rate / 2);
        }
        $items = [];
        foreach ($selectedServices as $item) {
            $service = Service::query()->where('branch_id', $court->branch_id)->where('active', true)->find($item['id']);
            if (! $service) {
                throw ValidationException::withMessages(['services' => 'Dịch vụ không hoạt động hoặc thuộc chi nhánh khác.']);
            }
            if ($service->name === 'Huấn luyện viên') {
                throw ValidationException::withMessages(['services' => 'Chọn người dạy và đặt buổi học trong mục Học cầu lông.']);
            }
            $items[$service->id] = [
                'name' => $service->name,
                'quantity' => (int) $item['quantity'],
                'unit_price' => $service->price,
                'amount' => $service->price * (int) $item['quantity'],
            ];
        }
        $serviceAmount = array_sum(array_column($items, 'amount'));
        $discounts = app(CommerceService::class)->discounts($actor, $courtAmount, $court->branch_id, $couponCode);
        $otherDiscount = min($courtAmount, $discounts['discount_amount']);
        $fixedDiscount = $fixed ? min($courtAmount - $otherDiscount, (int) floor($courtAmount * $court->branch->fixed_discount / 100)) : 0;
        $discount = $otherDiscount + $fixedDiscount;
        abort_if($courtAmount + $serviceAmount - $discount > 2147483647, 422, 'Tổng giá vượt giới hạn.');

        return ['court_amount' => $courtAmount, 'service_amount' => $serviceAmount, 'total_amount' => $courtAmount + $serviceAmount - $discount, 'discount_amount' => $discount, 'fixed_discount_amount' => $fixedDiscount, 'other_discount_amount' => $otherDiscount, 'coupon_id' => $discounts['coupon_id'], 'membership' => $discounts['membership'], 'items' => $items];
    }

    /** @return array<string, mixed> */
    public function seriesQuote(Court $court, CarbonImmutable $start, CarbonImmutable $end, int $occurrences, array $services, ?User $owner, ?User $actor): array
    {
        app(FeatureService::class)->expireBookings();
        $sessions = [];
        for ($week = 0; $week < $occurrences; $week++) {
            $begin = $start->addWeeks($week);
            $finish = $end->addWeeks($week);
            try {
                $this->assertOpen($court, $begin, $finish, $owner);
                $this->assertAvailable($court, $begin, $finish, null, $actor?->id);
                $quote = $this->quote($court, $begin, $finish, $services, $owner, null, true);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())->map(fn (array $messages) => array_map(fn (string $message) => 'Buổi thứ '.($week + 1).' ('.$begin->format('d/m/Y').'): '.$message, $messages))->all());
            }
            unset($quote['items']);
            $sessions[] = $quote + ['starts_at' => $begin->toIso8601String(), 'ends_at' => $finish->toIso8601String(), 'deposit_amount' => $actor?->role === 'customer' ? (int) ceil($quote['total_amount'] * $court->branch->deposit_percent / 100) : 0];
        }
        $totals = [];
        foreach (['court_amount', 'service_amount', 'discount_amount', 'fixed_discount_amount', 'other_discount_amount', 'total_amount', 'deposit_amount'] as $field) {
            $totals[$field] = array_sum(array_column($sessions, $field));
        }

        return $totals + ['occurrences' => $occurrences, 'fixed_discount_percent' => $court->branch->fixed_discount, 'deposit_percent' => $court->branch->deposit_percent, 'sessions' => $sessions];
    }

    public function assertManage(User $user, int $branchId, ?string $permission = null): void
    {
        abort_unless(app(AccessService::class)->manages($user, $branchId) && (! $permission || app(AccessService::class)->allows($user, $permission)), 403, 'Bạn không có quyền quản lý chi nhánh này.');
    }

    public function assertOpen(Court $court, CarbonImmutable $start, CarbonImmutable $end, ?User $owner = null): void
    {
        $branch = $court->branch;
        $startMinute = $start->hour * 60 + $start->minute;
        $endMinute = $end->hour * 60 + $end->minute;
        $early = $owner ? Membership::where('user_id', $owner->id)->where('expires_at', '>', now())->with('plan')->get()->max(fn ($m) => $m->plan->early_days) : 0;
        if ($startMinute < $branch->opens_minute || $endMinute > $branch->closes_minute) {
            $opens = sprintf('%02d:%02d', intdiv($branch->opens_minute, 60), $branch->opens_minute % 60);
            $closes = sprintf('%02d:%02d', intdiv($branch->closes_minute, 60), $branch->closes_minute % 60);
            throw ValidationException::withMessages(['starts_at' => 'Cơ sở mở cửa từ '.$opens.' đến '.$closes.'. Vui lòng chọn khung giờ trong khoảng này.']);
        }
        $lastDay = now()->addDays($branch->booking_days + ($early ?? 0))->endOfDay();
        if ($start->gt($lastDay)) {
            throw ValidationException::withMessages(['starts_at' => 'Cơ sở đang nhận lịch đến ngày '.$lastDay->format('d/m/Y').'. Chọn ngày sớm hơn hoặc giảm số tuần.']);
        }
    }

    public function create(User $actor, array $data): Booking
    {
        app(FeatureService::class)->expireBookings();
        [$start, $end] = $this->window($data['starts_at'], $data['ends_at']);
        try {
            return DB::transaction(function () use ($actor, $data, $start, $end): Booking {
                $court = Court::query()->lockForUpdate()->findOrFail($data['court_id']);
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                abort_unless($actor->active, 403);
                if ($actor->role !== 'customer') {
                    $this->assertManage($actor, $court->branch_id, 'bookings.write');
                    if (empty($data['user_id']) || (int) $data['user_id'] === $actor->id) {
                        throw ValidationException::withMessages(['user_id' => 'Chọn tài khoản khách hàng để đặt tại quầy. Tài khoản làm việc không dùng để đặt sân cá nhân.']);
                    }
                }
                $userId = $actor->id;
                if (isset($data['user_id']) && (int) $data['user_id'] !== $actor->id) {
                    $this->assertManage($actor, $court->branch_id, 'bookings.write');
                    $userId = User::query()->where('role', 'customer')->where('active', true)->findOrFail($data['user_id'])->id;
                }
                $existing = Booking::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    $expected = collect($data['services'] ?? [])->mapWithKeys(fn (array $item) => [(int) $item['id'] => (int) $item['quantity']])->sortKeys()->all();
                    $actual = $existing->services->mapWithKeys(fn (Service $service) => [$service->id => $service->pivot->quantity])->sortKeys()->all();
                    if ($existing->user_id !== $userId || $existing->court_id !== $court->id || ! $existing->starts_at->eq($start) || ! $existing->ends_at->eq($end) || $expected !== $actual || ($existing->notes ?? '') !== ($data['notes'] ?? '') || ($existing->coupon_code ?? '') !== strtoupper($data['coupon_code'] ?? '') || $existing->booking_type !== (isset($data['_fixed']) ? 'fixed' : 'single')) {
                        throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho một nội dung đặt sân khác.']);
                    }

                    return $existing->load(['court.branch', 'services', 'payments']);
                }
                if ($court->status !== 'active' || ! $court->branch->active) {
                    throw ValidationException::withMessages(['court_id' => 'Sân hiện không nhận đặt lịch.']);
                }
                if (isset($data['hold_id'])) {
                    $hold = CourtHold::query()->lockForUpdate()->findOrFail($data['hold_id']);
                    abort_unless($hold->user_id === $actor->id && $hold->court_id === $court->id && $hold->starts_at->eq($start) && $hold->ends_at->eq($end) && ! $hold->released && $hold->expires_at->gt(now()), 422, 'Giữ chỗ đã hết hạn hoặc không khớp lịch.');
                }
                if (! empty($data['coupon_code'])) {
                    Coupon::where('code', strtoupper($data['coupon_code']))->lockForUpdate()->first();
                }
                $this->assertOpen($court, $start, $end, User::findOrFail($userId));
                $this->assertAvailable($court, $start, $end, null, $actor->id);
                $quote = $this->quote($court, $start, $end, $data['services'] ?? [], User::findOrFail($userId), $data['coupon_code'] ?? null, isset($data['_fixed']));
                $booking = Booking::create([
                    'reference' => 'BH-'.strtoupper(Str::random(10)),
                    'request_key' => $data['request_key'],
                    'user_id' => $userId,
                    'court_id' => $court->id,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => 'confirmed',
                    'court_amount' => $quote['court_amount'],
                    'service_amount' => $quote['service_amount'],
                    'total_amount' => $quote['total_amount'],
                    'discount_amount' => $quote['discount_amount'], 'coupon_id' => $quote['coupon_id'], 'coupon_code' => isset($data['coupon_code']) ? strtoupper($data['coupon_code']) : null,
                    'booking_type' => isset($data['_fixed']) ? 'fixed' : 'single', 'series_key' => $data['_fixed'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'source' => $actor->role === 'customer' ? 'online' : 'counter',
                    'deposit_amount' => $actor->role === 'customer' ? (int) ceil($quote['total_amount'] * $court->branch->deposit_percent / 100) : 0,
                    'payment_expires_at' => $actor->role === 'customer' && $court->branch->deposit_percent > 0 ? ($start->lt(now()->addMinutes($court->branch->payment_minutes)) ? $start : now()->addMinutes($court->branch->payment_minutes)) : null,
                ]);
                $booking->services()->attach($quote['items']);
                CourtHold::where('user_id', $actor->id)->where('court_id', $court->id)->where('released', false)->update(['released' => true]);
                $this->audit($actor, 'booking.created', $booking->id, ['reference' => $booking->reference, 'total_amount' => $booking->total_amount]);
                $this->notifyBooking($booking, 'created', 'Đặt sân thành công', 'Lịch '.$booking->reference.' đã được xác nhận.');

                return $booking->load(['court.branch', 'services', 'payments']);
            }, 5);
        } catch (QueryException $exception) {
            if (Booking::where('request_key', $data['request_key'])->exists()) {
                throw ValidationException::withMessages(['request_key' => 'Yêu cầu đã được xử lý. Vui lòng xem lịch đặt sân của bạn.']);
            }
            throw $exception;
        }
    }

    public function assertAvailable(Court $court, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptBookingId = null, ?int $actorId = null, array $ignoreBookingIds = []): void
    {
        $booking = $court->bookings()->where('status', '!=', 'cancelled')->whereNotIn('id', $ignoreBookingIds)->when($exceptBookingId, fn ($query) => $query->where('id', '!=', $exceptBookingId))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first();
        $maintenance = $court->maintenances()->where('status', 'scheduled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first();
        $hold = CourtHold::where('court_id', $court->id)->where('released', false)->where('expires_at', '>', now())->when($actorId, fn ($query) => $query->where('user_id', '!=', $actorId))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        $event = DB::table('court_blocks')->where('court_id', $court->id)->where('active', true)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        if ($booking || $maintenance || $hold || $event) {
            throw ValidationException::withMessages(['starts_at' => 'Sân đã có lịch đặt hoặc lịch bảo trì trong khung giờ này.']);
        }
    }

    private function assertManageMove(User $actor, Booking $booking, Court $target): void
    {
        if ($target->branch_id !== $booking->court->branch_id) {
            throw ValidationException::withMessages(['court_id' => 'Chỉ đổi sân trong cùng chi nhánh.']);
        }
        if ($actor->role !== 'customer') {
            $this->assertManage($actor, $target->branch_id);
        }
        if (DB::table('coach_sessions')->where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'Chức năng đổi buổi học chưa hỗ trợ tự động. Vui lòng liên hệ chi nhánh để kiểm tra lịch người dạy trước khi thay đổi.']);
        }
    }

    public function reschedule(User $actor, Booking $booking, array $data, bool $preview = false): Booking
    {
        [$start, $end] = $this->window($data['starts_at'], $data['ends_at']);

        return DB::transaction(function () use ($actor, $booking, $start, $end, $data, $preview): Booking {
            $targetId = (int) ($data['court_id'] ?? $booking->court_id);
            $locked = Court::whereIn('id', [$booking->court_id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $court = $locked[$targetId];
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($actor->role === 'customer') {
                abort_unless($booking->user_id === $actor->id, 403);
                if ($booking->paid_amount > 0) {
                    $walletPaid = $booking->payments()->where('method', 'wallet')->get()->sum(fn ($payment) => $payment->kind === 'refund' ? -$payment->amount : $payment->amount);
                    abort_unless($walletPaid === $booking->paid_amount && $booking->starts_at->gte(now()->addHours(4)), 422, 'Lịch đã trả bằng phương thức khác hoặc còn dưới 4 tiếng cần chi nhánh hỗ trợ đổi.');
                }
            } else {
                $this->assertManage($actor, $court->branch_id, 'bookings.write');
            }
            if ($booking->status !== 'confirmed' || $booking->starts_at->lte(now())) {
                throw ValidationException::withMessages(['starts_at' => 'Chỉ đổi lịch đang xác nhận và chưa bắt đầu.']);
            }
            if ($court->status !== 'active' || ! $court->branch->active) {
                throw ValidationException::withMessages(['court_id' => 'Sân hiện không nhận đổi lịch.']);
            }
            if ($booking->court_id === $court->id && $booking->starts_at->eq($start) && $booking->ends_at->eq($end)) {
                return $booking->load(['court.branch', 'services', 'payments']);
            }
            $this->assertManageMove($actor, $booking, $court);
            $this->assertOpen($court, $start, $end, $booking->user);
            $this->assertAvailable($court, $start, $end, $booking->id, $actor->id, $data['_series_ids'] ?? []);
            $quote = $this->quote($court, $start, $end, [], $booking->user, null, $booking->booking_type === 'fixed');
            $quote['items'] = $booking->services->mapWithKeys(fn (Service $service) => [$service->id => ['name' => $service->pivot->name, 'quantity' => $service->pivot->quantity, 'unit_price' => $service->pivot->unit_price, 'amount' => $service->pivot->amount]])->all();
            $quote['service_amount'] = array_sum(array_column($quote['items'], 'amount'));
            $quote['total_amount'] += $quote['service_amount'];
            if ($booking->coupon_id) {
                throw ValidationException::withMessages(['starts_at' => 'Chức năng đổi lịch đã dùng mã ưu đãi chưa hỗ trợ tự động. Vui lòng liên hệ chi nhánh để kiểm tra giá và điều kiện mã.']);
            }
            abort_if(PaymentAttempt::where('booking_id', $booking->id)->where('partner_code', 'PAYOS')->where('status', 'pending')->where('expires_at', '>', now())->exists(), 409, 'Lịch đang có giao dịch chờ xác nhận. Hãy đối soát thanh toán trước khi đổi sân hoặc giờ.');
            $newTotal = $quote['total_amount'] + $booking->product_amount;
            if (isset($data['expected_total'])) {
                abort_if((int) $data['expected_total'] !== $newTotal, 409, 'Giá đã thay đổi. Vui lòng xem lại báo giá trước khi xác nhận.');
            }
            $refund = max(0, $booking->paid_amount - $newTotal);
            abort_if(isset($data['expected_refund']) && (int) $data['expected_refund'] !== $refund, 409, 'Khoản hoàn đã thay đổi. Vui lòng xem lại báo giá.');
            $previousCourt = $locked[$booking->court_id];
            $previous = ['court_id' => $booking->court_id, 'starts_at' => $booking->starts_at->toIso8601String(), 'ends_at' => $booking->ends_at->toIso8601String(), 'total_amount' => $booking->total_amount];
            abort_if($refund > 0 && ! $preview, 422, 'Khung giờ mới có giá thấp hơn khoản đã trả. Vui lòng chọn lịch có giá phù hợp hoặc liên hệ chi nhánh; hệ thống không xử lý hoàn tiền.');
            $booking->fill(['court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'court_amount' => $quote['court_amount'], 'service_amount' => $quote['service_amount'], 'total_amount' => $newTotal, 'discount_amount' => $quote['discount_amount'], 'deposit_amount' => $booking->source === 'online' ? (int) ceil($quote['total_amount'] * $court->branch->deposit_percent / 100) : 0, 'payment_expires_at' => $booking->payment_expires_at && $start->lt($booking->payment_expires_at) ? $start : $booking->payment_expires_at]);
            if ($preview) {
                return $booking->setRelation('court', $court);
            }
            $booking->save();
            $booking->services()->sync($quote['items']);
            $this->audit($actor, 'booking.rescheduled', $booking->id, ['previous' => $previous, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'total_amount' => $booking->total_amount]);
            $this->notifyBooking($booking, 'rescheduled', 'Đã đổi lịch sân', 'Lịch '.$booking->reference.' đã được cập nhật.');
            $this->notifyWaitlist($previousCourt, CarbonImmutable::parse($previous['starts_at']), CarbonImmutable::parse($previous['ends_at']));

            return $booking->load(['court.branch', 'services', 'payments']);
        }, 5);
    }

    public function changeStatus(User $actor, Booking $booking, string $status): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $status): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($actor->role === 'customer') {
                abort_unless($booking->user_id === $actor->id, 403);
                abort_if($status === 'cancelled' && $booking->paid_amount > 0, 422, 'Lịch đã có thanh toán không thể hủy trực tiếp. Vui lòng liên hệ chi nhánh để đổi lịch.');
                if ($booking->status === 'cancelled' && $status === 'cancelled') {
                    return $booking->load(['court.branch', 'services', 'payments']);
                }
                if ($status !== 'cancelled' || $booking->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['status' => 'Chỉ tự hủy lịch trước giờ chơi. Lịch trả bằng ví cần hủy trước ít nhất 4 tiếng; phương thức khác cần quầy xử lý.']);
                }
                if ($booking->paid_amount > 0) {
                    if ($booking->starts_at->lt(now()->addHours(4))) {
                        throw ValidationException::withMessages(['status' => 'Lịch đã trả tiền cần hủy trước giờ chơi tối thiểu 4 tiếng.']);
                    }
                    app(CommerceService::class)->refundWalletBooking($actor, $booking);
                }
            } else {
                $this->assertManage($actor, $booking->court->branch_id, 'bookings.write');
            }
            if ($booking->status === $status) {
                return $booking->load(['court.branch', 'services', 'payments']);
            }
            abort_if($status === 'cancelled' && $booking->paid_amount > 0, 422, 'Lịch đã có thanh toán không thể hủy trực tiếp. Vui lòng liên hệ chi nhánh để đổi lịch.');
            if ($status === 'cancelled' && $booking->status === 'confirmed') {
                $booking->status = 'cancelled';
            } elseif ($status === 'checked_in' && $booking->status === 'confirmed' && $booking->paid_amount === $booking->total_amount && now()->between($booking->starts_at->subMinutes(30), $booking->ends_at)) {
                $booking->status = 'checked_in';
                $booking->checked_in_at = now();
            } elseif ($status === 'completed' && $booking->paid_amount === $booking->total_amount && $booking->status === 'checked_in' && now()->gte($booking->ends_at)) {
                $booking->status = 'completed';
            } else {
                throw ValidationException::withMessages(['status' => 'Không thể đổi trạng thái. Check-in yêu cầu thanh toán đủ và đúng giờ; chỉ hoàn tất khi hết giờ chơi.']);
            }
            $booking->save();
            if ($status === 'completed') {
                app(LoyaltyService::class)->award($booking->user, $booking->paid_amount, 'booking-'.$booking->id, 'Hoàn thành '.$booking->reference);
            }
            if ($status === 'cancelled') {
                DB::table('coach_sessions')->where('booking_id', $booking->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            $this->audit($actor, 'booking.'.$status, $booking->id, ['paid_amount' => $booking->paid_amount]);
            $titles = ['cancelled' => 'Đã hủy lịch sân', 'checked_in' => 'Đã nhận sân', 'completed' => 'Buổi chơi đã hoàn tất'];
            $this->notifyBooking($booking, $status, $titles[$status], 'Trạng thái lịch '.$booking->reference.' đã được cập nhật.');
            if ($status === 'cancelled') {
                $this->notifyWaitlist($booking->court, $booking->starts_at, $booking->ends_at);
            }

            return $booking->load(['court.branch', 'services', 'payments']);
        }, 5);
    }

    public function recordPayment(User $actor, Booking $booking, array $data): Payment
    {
        return DB::transaction(function () use ($actor, $booking, $data): Payment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->assertManage($actor, $booking->court->branch_id, 'payments.write');
            $kind = $data['kind'] ?? 'payment';
            abort_unless($kind === 'payment' && in_array($data['method'], ['cash', 'bank'], true), 422, 'Chỉ ghi nhận khoản thanh toán trực tiếp tại quầy bằng tiền mặt hoặc chuyển khoản.');
            $existing = Payment::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->booking_id !== $booking->id || $existing->amount !== (int) $data['amount'] || $existing->kind !== $kind || $existing->method !== $data['method']) {
                    throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho giao dịch khác.']);
                }

                return $existing;
            }
            $amount = (int) $data['amount'];
            if ($kind === 'refund') {
                abort_if(DB::table('refund_requests')->where('booking_id', $booking->id)->where('status', 'pending')->exists(), 409, 'Đang có yêu cầu hoàn tiền cũ chờ đối soát. Cần kiểm tra để tránh hoàn tiền hai lần.');
                if ($booking->status !== 'cancelled' || $amount > $booking->paid_amount) {
                    throw ValidationException::withMessages(['amount' => 'Chỉ hoàn tiền lịch đã hủy và không vượt số tiền đã thu.']);
                }
                $booking->paid_amount -= $amount;
            } else {
                if ($booking->status === 'cancelled' || $amount > $booking->total_amount - $booking->paid_amount) {
                    throw ValidationException::withMessages(['amount' => 'Không thu tiền lịch đã hủy hoặc thu vượt số tiền còn thiếu.']);
                }
                $booking->paid_amount += $amount;
            }
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'received_by' => $actor->id,
                'request_key' => $data['request_key'],
                'kind' => $kind,
                'method' => $data['method'],
                'amount' => $amount,
                'notes' => $data['notes'] ?? null,
            ]);
            $booking->save();
            $this->audit($actor, 'booking.'.$kind, $booking->id, ['amount' => $amount, 'method' => $data['method'], 'payment_id' => $payment->id]);
            $this->notifyBooking($booking, $kind, $kind === 'refund' ? 'Đã hoàn tiền' : 'Đã ghi nhận thanh toán', 'Lịch '.$booking->reference.': '.number_format($amount, 0, ',', '.').' đồng.');

            return $payment;
        }, 5);
    }

    public function addServices(User $actor, Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $data): Booking {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $this->assertManage($actor, $booking->court->branch_id, 'bookings.write');
            $items = collect($data['services'])->mapWithKeys(fn ($row) => [(int) $row['id'] => (int) $row['quantity']])->sortKeys()->all();
            $existing = DB::table('audit_logs')->where('action', 'booking.services_added')->where('details->request_key', $data['request_key'])->first();
            if ($existing) {
                $saved = json_decode($existing->details, true);
                abort_unless((int) $existing->subject_id === $booking->id && (int) $existing->user_id === $actor->id && $saved['items'] == $items, 409, 'Yêu cầu đã dùng để thêm dịch vụ khác.');

                return $booking->load('court.branch', 'services', 'payments');
            }
            abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Hóa đơn đã chốt hoặc lịch đã hủy. Không thể thêm dịch vụ.');
            $sum = 0;
            foreach ($items as $id => $quantity) {
                $service = Service::where('branch_id', $booking->court->branch_id)->where('active', true)->findOrFail($id);
                abort_if($service->name === 'Huấn luyện viên' || str_starts_with($service->name, 'Buổi học cùng '), 422, 'Buổi học cần được đặt trong mục Học cầu lông.');
                $previous = $booking->services()->where('services.id', $id)->first()?->pivot;
                $unitPrice = $previous ? (int) $previous->unit_price : (int) $service->price;
                $count = ($previous ? (int) $previous->quantity : 0) + $quantity;
                abort_if($count > 100, 422, 'Mỗi dịch vụ tối đa 100 đơn vị trong một hóa đơn.');
                $sum += $unitPrice * $quantity;
                $booking->services()->syncWithoutDetaching([$id => ['name' => $previous?->name ?? $service->name, 'quantity' => $count, 'unit_price' => $unitPrice, 'amount' => $unitPrice * $count]]);
            }
            abort_if($booking->total_amount + $sum > 2147483647, 422, 'Tổng hóa đơn vượt giới hạn.');
            $booking->service_amount += $sum;
            $booking->total_amount += $sum;
            $booking->save();
            $this->audit($actor, 'booking.services_added', $booking->id, ['request_key' => $data['request_key'], 'items' => $items, 'amount' => $sum]);

            return $booking->load('court.branch', 'services', 'payments');
        }, 5);
    }

    private function notifyBooking(Booking $booking, string $action, string $title, string $body): void
    {
        $booking->user->notify(new HubNotification(['type' => 'booking.'.$action, 'title' => $title, 'body' => $body, 'booking_id' => $booking->id, 'reference' => $booking->reference]));
    }

    public function notifyWaitlist(Court $court, CarbonImmutable $start, CarbonImmutable $end): void
    {
        foreach (WaitlistEntry::with('user')->where('court_id', $court->id)->where('status', 'waiting')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where('starts_at', '>', now())->get() as $entry) {
            try {
                $this->assertAvailable($court, $entry->starts_at, $entry->ends_at);
            } catch (ValidationException) {
                continue;
            }
            $entry->user->notify(new HubNotification(['type' => 'waitlist.available', 'title' => 'Sân trong danh sách chờ vừa trống', 'body' => $court->name.' · '.$entry->starts_at->format('d/m H:i').'. Bạn có thể kiểm tra và đặt sân.', 'court_id' => $court->id]));
            $entry->update(['status' => 'notified']);
        }
    }

    public function audit(User $actor, string $action, int $subjectId, array $details, string $subjectType = 'booking'): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
