<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('CP-######')), 'kind' => 'percent', 'value' => 10, 'minimum_amount' => 0, 'usage_limit' => 100, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDay(), 'active' => true];
    }
}
