<?php

namespace App;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
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
        if ($start->lt(now()->startOfMinute()) || ! $start->isSameDay($end) || $start->hour < 3 || $end->hour > 23 || ($end->hour === 23 && $end->minute > 0) || $minutes < 30 || $minutes > 360 || $start->minute % 30 !== 0 || $end->minute % 30 !== 0 || $start->second !== 0 || $end->second !== 0) {
            throw ValidationException::withMessages(['starts_at' => 'Chọn lịch tương lai, từ 03:00 đến 23:00, theo bước 30 phút và tối đa 6 giờ trong một ngày.']);
        }

        return [$start, $end];
    }

    /** @return array<string, mixed> */
    public function quote(Court $court, CarbonImmutable $start, CarbonImmutable $end, array $selectedServices = []): array
    {
        $courtAmount = 0;
        for ($slot = $start; $slot->lt($end); $slot = $slot->addMinutes(30)) {
            $rate = $slot->isWeekend() ? $court->weekend_rate : ($slot->hour >= 16 ? $court->peak_rate : $court->hourly_rate);
            $courtAmount += (int) round($rate / 2);
        }
        $items = [];
        foreach ($selectedServices as $item) {
            $service = Service::query()->where('branch_id', $court->branch_id)->where('active', true)->find($item['id']);
            if (! $service) {
                throw ValidationException::withMessages(['services' => 'Dịch vụ không hoạt động hoặc thuộc chi nhánh khác.']);
            }
            $items[$service->id] = [
                'name' => $service->name,
                'quantity' => (int) $item['quantity'],
                'unit_price' => $service->price,
                'amount' => $service->price * (int) $item['quantity'],
            ];
        }
        $serviceAmount = array_sum(array_column($items, 'amount'));

        return ['court_amount' => $courtAmount, 'service_amount' => $serviceAmount, 'total_amount' => $courtAmount + $serviceAmount, 'items' => $items];
    }

    public function assertManage(User $user, int $branchId): void
    {
        abort_unless($user->role === 'admin' || ($user->role === 'manager' && $user->branch_id === $branchId), 403, 'Bạn không có quyền quản lý chi nhánh này.');
    }

    public function create(User $actor, array $data): Booking
    {
        [$start, $end] = $this->window($data['starts_at'], $data['ends_at']);
        try {
            return DB::transaction(function () use ($actor, $data, $start, $end): Booking {
                $court = Court::query()->lockForUpdate()->findOrFail($data['court_id']);
                if ($actor->role !== 'customer') {
                    $this->assertManage($actor, $court->branch_id);
                }
                $userId = $actor->id;
                if (isset($data['user_id']) && (int) $data['user_id'] !== $actor->id) {
                    $this->assertManage($actor, $court->branch_id);
                    $userId = User::query()->where('role', 'customer')->findOrFail($data['user_id'])->id;
                }
                $existing = Booking::query()->where('request_key', $data['request_key'])->first();
                if ($existing) {
                    $expected = collect($data['services'] ?? [])->mapWithKeys(fn (array $item) => [(int) $item['id'] => (int) $item['quantity']])->sortKeys()->all();
                    $actual = $existing->services->mapWithKeys(fn (Service $service) => [$service->id => $service->pivot->quantity])->sortKeys()->all();
                    if ($existing->user_id !== $userId || $existing->court_id !== $court->id || ! $existing->starts_at->eq($start) || ! $existing->ends_at->eq($end) || $expected !== $actual || ($existing->notes ?? '') !== ($data['notes'] ?? '')) {
                        throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho một nội dung đặt sân khác.']);
                    }

                    return $existing->load(['court.branch', 'services', 'payments']);
                }
                if ($court->status !== 'active' || ! $court->branch->active) {
                    throw ValidationException::withMessages(['court_id' => 'Sân hiện không nhận đặt lịch.']);
                }
                $this->assertAvailable($court, $start, $end);
                $quote = $this->quote($court, $start, $end, $data['services'] ?? []);
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
                    'notes' => $data['notes'] ?? null,
                ]);
                $booking->services()->attach($quote['items']);
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

    public function assertAvailable(Court $court, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptBookingId = null): void
    {
        $booking = $court->bookings()->where('status', '!=', 'cancelled')->when($exceptBookingId, fn ($query) => $query->where('id', '!=', $exceptBookingId))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first();
        $maintenance = $court->maintenances()->where('status', 'scheduled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first();
        if ($booking || $maintenance) {
            throw ValidationException::withMessages(['starts_at' => 'Sân đã có lịch đặt hoặc lịch bảo trì trong khung giờ này.']);
        }
    }

    public function reschedule(User $actor, Booking $booking, array $data): Booking
    {
        [$start, $end] = $this->window($data['starts_at'], $data['ends_at']);

        return DB::transaction(function () use ($actor, $booking, $start, $end): Booking {
            $court = Court::query()->lockForUpdate()->findOrFail($booking->court_id);
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($actor->role === 'customer') {
                abort_unless($booking->user_id === $actor->id, 403);
            } else {
                $this->assertManage($actor, $court->branch_id);
            }
            if ($booking->status !== 'confirmed' || $booking->paid_amount > 0 || $booking->starts_at->lte(now())) {
                throw ValidationException::withMessages(['starts_at' => 'Chỉ đổi lịch chưa thanh toán, chưa bắt đầu và đang xác nhận.']);
            }
            if ($court->status !== 'active' || ! $court->branch->active) {
                throw ValidationException::withMessages(['court_id' => 'Sân hiện không nhận đổi lịch.']);
            }
            if ($booking->starts_at->eq($start) && $booking->ends_at->eq($end)) {
                return $booking->load(['court.branch', 'services', 'payments']);
            }
            $this->assertAvailable($court, $start, $end, $booking->id);
            $services = $booking->services->map(fn (Service $service) => ['id' => $service->id, 'quantity' => $service->pivot->quantity])->all();
            $quote = $this->quote($court, $start, $end, $services);
            $previous = ['starts_at' => $booking->starts_at->toIso8601String(), 'ends_at' => $booking->ends_at->toIso8601String(), 'total_amount' => $booking->total_amount];
            $booking->fill(['starts_at' => $start, 'ends_at' => $end, 'court_amount' => $quote['court_amount'], 'service_amount' => $quote['service_amount'], 'total_amount' => $quote['total_amount']])->save();
            $booking->services()->sync($quote['items']);
            $this->audit($actor, 'booking.rescheduled', $booking->id, ['previous' => $previous, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'total_amount' => $booking->total_amount]);
            $this->notifyBooking($booking, 'rescheduled', 'Đã đổi lịch sân', 'Lịch '.$booking->reference.' đã được cập nhật.');

            return $booking->load(['court.branch', 'services', 'payments']);
        }, 5);
    }

    public function changeStatus(User $actor, Booking $booking, string $status): Booking
    {
        return DB::transaction(function () use ($actor, $booking, $status): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if ($actor->role === 'customer') {
                abort_unless($booking->user_id === $actor->id, 403);
                if ($status !== 'cancelled' || $booking->starts_at->lte(now()) || $booking->paid_amount > 0) {
                    throw ValidationException::withMessages(['status' => 'Chỉ tự hủy lịch chưa thanh toán, trước giờ chơi. Lịch đã trả tiền cần liên hệ quầy.']);
                }
            } else {
                $this->assertManage($actor, $booking->court->branch_id);
            }
            if ($booking->status === $status) {
                return $booking->load(['court.branch', 'services', 'payments']);
            }
            if ($status === 'cancelled' && $booking->status === 'confirmed') {
                $booking->status = 'cancelled';
            } elseif ($status === 'checked_in' && $booking->status === 'confirmed' && $booking->paid_amount === $booking->total_amount && now()->between($booking->starts_at->subMinutes(30), $booking->ends_at)) {
                $booking->status = 'checked_in';
                $booking->checked_in_at = now();
            } elseif ($status === 'completed' && $booking->status === 'checked_in' && now()->gte($booking->ends_at)) {
                $booking->status = 'completed';
            } else {
                throw ValidationException::withMessages(['status' => 'Không thể đổi trạng thái. Check-in yêu cầu thanh toán đủ và đúng giờ; chỉ hoàn tất khi hết giờ chơi.']);
            }
            $booking->save();
            $this->audit($actor, 'booking.'.$status, $booking->id, ['paid_amount' => $booking->paid_amount]);
            $titles = ['cancelled' => 'Đã hủy lịch sân', 'checked_in' => 'Đã nhận sân', 'completed' => 'Buổi chơi đã hoàn tất'];
            $this->notifyBooking($booking, $status, $titles[$status], 'Trạng thái lịch '.$booking->reference.' đã được cập nhật.');

            return $booking->load(['court.branch', 'services', 'payments']);
        }, 5);
    }

    public function recordPayment(User $actor, Booking $booking, array $data): Payment
    {
        return DB::transaction(function () use ($actor, $booking, $data): Payment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->assertManage($actor, $booking->court->branch_id);
            $kind = $data['kind'] ?? 'payment';
            $existing = Payment::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->booking_id !== $booking->id || $existing->amount !== (int) $data['amount'] || $existing->kind !== $kind || $existing->method !== $data['method']) {
                    throw ValidationException::withMessages(['request_key' => 'Mã yêu cầu đã dùng cho giao dịch khác.']);
                }

                return $existing;
            }
            $amount = (int) $data['amount'];
            if ($kind === 'refund') {
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

    private function notifyBooking(Booking $booking, string $action, string $title, string $body): void
    {
        $booking->user->notify(new HubNotification(['type' => 'booking.'.$action, 'title' => $title, 'body' => $body, 'booking_id' => $booking->id, 'reference' => $booking->reference]));
    }

    public function audit(User $actor, string $action, int $subjectId, array $details, string $subjectType = 'booking'): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
