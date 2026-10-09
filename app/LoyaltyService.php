<?php

namespace App;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class LoyaltyService
{
    public function award(User $owner, int $amount, string $key, string $description): void
    {
        $points = (int) floor($amount / 10000);
        if ($points < 1 || $owner->role !== 'customer') {
            return;
        }
        DB::transaction(function () use ($owner, $points, $key, $description): void {
            $owner = User::lockForUpdate()->findOrFail($owner->id);
            if (DB::table('loyalty_entries')->where('request_key', $key)->exists()) {
                return;
            }
            $owner->loyalty_points += $points;
            $owner->earned_points += $points;
            $owner->save();
            DB::table('loyalty_entries')->insert(['user_id' => $owner->id, 'request_key' => $key, 'points' => $points, 'balance' => $owner->loyalty_points, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            $tier = MembershipPlan::where('active', true)->where('points_threshold', '>', 0)->where('points_threshold', '<=', $owner->earned_points)->orderByDesc('discount_percent')->first();
            if ($tier) {
                $uuid = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'loyalty-tier-'.$owner->id.'-'.$tier->id);
                Membership::firstOrCreate(['request_key' => $uuid], ['user_id' => $owner->id, 'membership_plan_id' => $tier->id, 'amount' => 0, 'method' => 'wallet', 'starts_at' => now(), 'expires_at' => now()->addYear()]);
            }
        }, 5);
    }

    public function redeem(User $owner, int $rewardId, string $key): array
    {
        return DB::transaction(function () use ($owner, $rewardId, $key): array {
            $owner = User::lockForUpdate()->findOrFail($owner->id);
            $existing = DB::table('loyalty_entries')->where('request_key', $key)->first();
            if ($existing) {
                abort_unless($existing->user_id === $owner->id && $existing->description === 'Đổi quà #'.$rewardId, 422);

                return (array) $existing;
            }
            $reward = DB::table('loyalty_rewards')->where('active', true)->lockForUpdate()->find($rewardId);
            abort_unless($reward, 404);
            abort_if($owner->loyalty_points < $reward->points, 422, 'Bạn chưa đủ điểm đổi quà.');
            $owner->loyalty_points -= $reward->points;
            $owner->save();
            $id = DB::table('loyalty_entries')->insertGetId(['user_id' => $owner->id, 'request_key' => $key, 'points' => -$reward->points, 'balance' => $owner->loyalty_points, 'description' => 'Đổi quà #'.$rewardId, 'created_at' => now(), 'updated_at' => now()]);
            app(CommerceService::class)->wallet($owner, $owner, $reward->wallet_amount, 'reward', $key, $reward->name, null, 'loyalty', $id);

            return (array) DB::table('loyalty_entries')->find($id);
        }, 5);
    }
}
