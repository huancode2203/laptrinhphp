<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Chi nhánh thử '.fake()->unique()->city(), 'district' => fake()->city(), 'address' => fake()->address(), 'amenities' => ['WiFi'], 'active' => true];
    }
}
