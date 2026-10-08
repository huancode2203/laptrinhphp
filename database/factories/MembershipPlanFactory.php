<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipPlan> */
class MembershipPlanFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'price' => 199000, 'discount_percent' => 5, 'duration_days' => 30, 'active' => true];
    }
}
