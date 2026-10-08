<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => fake()->words(3, true), 'sku' => fake()->unique()->bothify('SKU-######'), 'category' => 'Nước uống', 'icon' => '💧', 'price' => 10000, 'stock' => 20, 'active' => true];
    }
}
