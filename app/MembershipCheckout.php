<?php

namespace App;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;

class MembershipCheckout
{
    public function prepare(User $owner, array $data): array
    {
        $actor = User::findOrFail($data['actor_id']);
        $branchId = (int) $data['branch_id'];
        app(BookingService::class)->assertManage($actor, $branchId, 'payments.write');
        app(BookingService::class)->assertManage($actor, $branchId, 'customers.read');
        $plan = MembershipPlan::lockForUpdate()->findOrFail($data['membership_plan_id']);
        abort_unless($plan->active && $plan->price >= 1000 && $plan->price <= 50000000, 422, 'Gói hội viên không còn mở bán hoặc giá chưa phù hợp để thanh toán PayOS.');
        abort_if($plan->points_threshold > $owner->earned_points, 422, 'Khách hàng chưa đủ điểm để mua gói hội viên này.');
        abort_if(PaymentAttempt::where('user_id', $owner->id)->where('purpose', 'membership')->where('status', 'pending')->where(fn ($q) => $q->where('expires_at', '>', now())->orWhereNotNull('review_reason'))->whereIn('id', DB::table('membership_payment_orders')->where('membership_plan_id', $plan->id)->select('payment_attempt_id'))->exists(), 409, 'Khách đang có thanh toán cho gói này. Hãy kiểm tra hoặc hủy giao dịch cũ trước khi tạo mới.');

        return ['amount' => $plan->price, 'membership_plan_id' => $plan->id, 'branch_id' => $branchId, 'initiated_by' => $actor->id, 'plan_name' => $plan->name, 'duration_days' => $plan->duration_days];
    }

    public function allocate(PaymentAttempt $attempt, User $owner, int $received, int $previous): int
    {
        $order = DB::table('membership_payment_orders')->where('payment_attempt_id', $attempt->id)->lockForUpdate()->first();
        abort_unless($order, 409, 'Thiếu thông tin gói hội viên. Vui lòng liên hệ chi nhánh để đối chiếu.');
        if ($order->membership_id || $attempt->status === 'failed' || $received < $attempt->amount || $previous > 0) {
            return 0;
        }
        $expires = Membership::where('user_id', $owner->id)->where('membership_plan_id', $order->membership_plan_id)->where('expires_at', '>', now())->latest('expires_at')->first()?->expires_at ?? now();
        $member = Membership::create(['user_id' => $owner->id, 'membership_plan_id' => $order->membership_plan_id, 'request_key' => $attempt->request_key, 'amount' => $attempt->amount, 'method' => 'payos', 'starts_at' => now(), 'expires_at' => $expires->addDays($order->duration_days)]);
        $actor = User::findOrFail($order->initiated_by);
        app(CommerceService::class)->payment($actor, $owner, 'membership', $member->id, $attempt->amount, 'payos', $order->branch_id);
        DB::table('membership_payment_orders')->where('id', $order->id)->update(['membership_id' => $member->id, 'updated_at' => now()]);
        app(BookingService::class)->audit($actor, 'membership.activated', $member->id, ['user_id' => $owner->id, 'plan' => $order->plan_name, 'amount' => $attempt->amount, 'method' => 'payos'], 'membership');
        $owner->notify(new HubNotification(['type' => 'membership.activated', 'title' => 'Đã kích hoạt hội viên', 'body' => 'Đã nhận thanh toán PayOS. Gói '.$order->plan_name.' có hiệu lực đến '.$member->expires_at->format('d-m-Y').'.']));

        return $attempt->amount;
    }

    public function branch(PaymentAttempt $attempt): ?int
    {
        return DB::table('membership_payment_orders')->where('payment_attempt_id', $attempt->id)->value('branch_id');
    }
}
