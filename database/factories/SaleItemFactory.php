<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleItem> */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        return ['sale_id' => Sale::factory(), 'product_id' => Product::factory(), 'name' => 'Nước suối', 'quantity' => 1, 'unit_price' => 10000, 'amount' => 10000];
    }
}
