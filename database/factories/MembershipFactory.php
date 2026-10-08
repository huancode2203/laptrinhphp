<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'membership_plan_id' => MembershipPlan::factory(), 'request_key' => (string) Str::uuid(), 'amount' => 199000, 'method' => 'cash', 'starts_at' => now(), 'expires_at' => now()->addDays(30)];
    }
}
