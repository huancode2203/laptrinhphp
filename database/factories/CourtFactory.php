<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Court> */
class CourtFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => 'Sân '.fake()->unique()->numerify('###'), 'status' => 'active', 'hourly_rate' => 100000, 'peak_rate' => 180000, 'weekend_rate' => 200000];
    }
}
