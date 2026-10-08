<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return ['request_key' => (string) Str::uuid(), 'reference' => 'POS-'.strtoupper(Str::random(10)), 'branch_id' => Branch::factory(), 'clerk_id' => User::factory(), 'total_amount' => 10000, 'method' => 'cash'];
    }
}
