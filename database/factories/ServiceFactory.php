<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => fake()->unique()->word(), 'unit' => 'chai', 'price' => 10000, 'active' => true];
    }
}
