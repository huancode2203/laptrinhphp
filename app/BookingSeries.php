<?php

namespace App;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class BookingSeries
{
    public function operate(User $actor, Booking $anchor, array $data, string $operation, bool $preview = false): array
    {
        $engine = app(BookingService::class);
        if ($actor->role === 'customer') {
            abort_unless($anchor->user_id === $actor->id, 403);
        } else {
            $engine->assertManage($actor, $anchor->court->branch_id, 'bookings.write');
        }
        abort_unless($anchor->series_key, 422, 'Lịch này không thuộc một chuỗi cố định.');
        $fingerprint = hash('sha256', json_encode([$operation, $anchor->id, $data['court_id'] ?? null, $data['starts_at'] ?? null, $data['ends_at'] ?? null, $data['refund_method'] ?? null]));

        return DB::transaction(function () use ($actor, $anchor, $data, $operation, $preview, $engine, $fingerprint): array {
            if (! $preview) {
                abort_unless(isset($data['request_key']), 422, 'Vui lòng tải lại bảng xem trước và xác nhận thao tác.');
                $prior = DB::table('audit_logs')->where('action', 'booking.series_'.$operation)->where('details->request_key', $data['request_key'])->first();
                if ($prior) {
                    $details = json_decode($prior->details, true);
                    abort_unless($prior->user_id === $actor->id && ($details['fingerprint'] ?? null) === $fingerprint, 409, 'Yêu cầu đã dùng cho thao tác khác.');

                    return $details['result'];
                }
            }
            $members = Booking::where('series_key', $anchor->series_key)->where('user_id', $anchor->user_id)->where('status', 'confirmed')->where('starts_at', '>', now())->orderBy('starts_at')->get();
            abort_if($members->isEmpty() || ! $members->contains('id', $anchor->id), 422, 'Chuỗi không còn buổi chưa bắt đầu để xử lý.');
            abort_if($members->count() > 52, 422, 'Mỗi lần xử lý tối đa 52 buổi.');
            $courtIds = $members->pluck('court_id')->push($data['court_id'] ?? $anchor->court_id)->unique()->sort()->values();
            Court::whereIn('id', $courtIds)->orderBy('id')->lockForUpdate()->get();
            $members = Booking::whereIn('id', $members->pluck('id'))->orderBy('starts_at')->lockForUpdate()->get();
            abort_if($members->contains(fn ($member) => $member->status !== 'confirmed' || $member->starts_at->lte(now())), 409, 'Một buổi trong chuỗi vừa thay đổi. Vui lòng tải lại.');
            $anchor = $members->firstWhere('id', $anchor->id);
            $offset = $operation === 'move' ? (int) $anchor->starts_at->diffInSeconds(CarbonImmutable::parse($data['starts_at']), false) : 0;
            $duration = $operation === 'move' ? (int) CarbonImmutable::parse($data['starts_at'])->diffInSeconds(CarbonImmutable::parse($data['ends_at'])) : 0;
            $plans = [];
            foreach ($members as $member) {
                if ($operation === 'move') {
                    $start = $member->starts_at->addSeconds($offset);
                    $row = [...$data, 'starts_at' => $start->toIso8601String(), 'ends_at' => $start->addSeconds($duration)->toIso8601String(), '_series_ids' => $members->pluck('id')->all()];
                    unset($row['expected_total'], $row['expected_refund']);
                    $quoted = $engine->reschedule($actor, $member, $row, true);
                    foreach ($plans as $previous) {
                        abort_if($previous['booking']->starts_at->lt($quoted->ends_at) && $previous['booking']->ends_at->gt($quoted->starts_at), 422, 'Các buổi trong chuỗi sẽ trùng nhau sau khi đổi. Vui lòng chọn khung giờ khác.');
                    }
                    $plans[] = ['booking' => $quoted, 'data' => $row];
                } else {
                    if ($actor->role === 'customer' && $member->paid_amount > 0) {
                        $wallet = $member->payments()->where('method', 'wallet')->get()->sum(fn ($payment) => $payment->kind === 'refund' ? -$payment->amount : $payment->amount);
                        abort_unless($member->starts_at->gte(now()->addHours(4)) && $wallet === $member->paid_amount, 422, 'Một buổi trong chuỗi cần chi nhánh hỗ trợ hủy và hoàn tiền.');
                    }
                    $plans[] = ['booking' => $member, 'data' => []];
                }
            }
            $result = $this->summary($plans, $members, $operation);
            if ($preview) {
                return $result;
            }
            abort_if(isset($data['expected_refund']) && (int) $data['expected_refund'] !== $result['refund_amount'], 409, 'Khoản hoàn đã thay đổi. Vui lòng xem lại bảng tính.');
            abort_if(isset($data['expected_total']) && (int) $data['expected_total'] !== $result['booking']['total_amount'], 409, 'Tổng tiền chuỗi đã thay đổi. Vui lòng xem lại bảng tính.');
            foreach ($plans as $plan) {
                $member = $plan['booking'];
                if ($operation === 'move') {
                    $engine->reschedule($actor, $members->firstWhere('id', $member->id), $plan['data']);
                } else {
                    $cancelled = $engine->changeStatus($actor, $member, 'cancelled');
                    $method = $data['refund_method'] ?? null;
                    if ($actor->role !== 'customer' && $cancelled->paid_amount > 0 && $method) {
                        $engine->assertManage($actor, $cancelled->court->branch_id, 'payments.write');
                        abort_if(DB::table('refund_requests')->where('booking_id', $cancelled->id)->where('status', 'pending')->exists(), 409, 'Chuỗi có khoản hoàn đang chờ đối soát.');
                        $key = (string) Uuid::uuid5($data['request_key'], 'series-refund-'.$cancelled->id);
                        if ($method === 'wallet') {
                            app(CommerceService::class)->wallet($actor, $cancelled->user, $cancelled->paid_amount, 'refund', $key, 'Hoàn hủy chuỗi '.$cancelled->reference, $cancelled->court->branch_id, 'booking', $cancelled->id);
                            Payment::create(['booking_id' => $cancelled->id, 'received_by' => $actor->id, 'request_key' => $key, 'kind' => 'refund', 'method' => 'wallet', 'amount' => $cancelled->paid_amount]);
                            $cancelled->update(['paid_amount' => 0]);
                        } else {
                            abort_unless(! empty($data['refund_confirmed']), 422, 'Chỉ ghi nhận khoản hoàn khi đã trả đủ tiền cho khách.');
                            $engine->recordPayment($actor, $cancelled, ['request_key' => $key, 'kind' => 'refund', 'method' => $method, 'amount' => $cancelled->paid_amount, 'notes' => 'Hoàn hủy chuỗi cố định']);
                        }
                    }
                }
            }
            $fresh = Booking::whereIn('id', $members->pluck('id'))->get()->keyBy('id');
            foreach ($result['sessions'] as &$session) {
                $session['paid_amount'] = $fresh[$session['id']]->paid_amount;
                $session['status'] = $fresh[$session['id']]->status;
            }
            unset($session);
            $result['booking']['paid_amount'] = $fresh->sum('paid_amount');
            $result['refund_pending_amount'] = $operation === 'cancel' ? $fresh->sum('paid_amount') : 0;
            $result['refunded_amount'] = $result['refund_amount'] - $result['refund_pending_amount'];
            $engine->audit($actor, 'booking.series_'.$operation, $anchor->id, ['request_key' => $data['request_key'], 'fingerprint' => $fingerprint, 'result' => $result]);

            return $result;
        }, 5);
    }

    private function summary(array $plans, Collection $members, string $operation): array
    {
        $sessions = array_map(fn ($plan) => ['id' => $plan['booking']->id, 'reference' => $plan['booking']->reference, 'starts_at' => $plan['booking']->starts_at->toIso8601String(), 'ends_at' => $plan['booking']->ends_at->toIso8601String(), 'total_amount' => $plan['booking']->total_amount, 'paid_amount' => $plan['booking']->paid_amount, 'refund_amount' => $operation === 'cancel' ? $plan['booking']->paid_amount : max(0, $plan['booking']->paid_amount - $plan['booking']->total_amount)], $plans);
        $total = $operation === 'cancel' ? 0 : array_sum(array_column($sessions, 'total_amount'));

        return ['sessions' => $sessions, 'booking' => ['total_amount' => $total, 'paid_amount' => $members->sum('paid_amount'), 'remaining_amount' => $operation === 'cancel' ? 0 : array_sum(array_map(fn ($session) => max(0, $session['total_amount'] - $session['paid_amount']), $sessions))], 'refund_amount' => array_sum(array_column($sessions, 'refund_amount')), 'difference' => $total - $members->sum('total_amount')];
    }
}
