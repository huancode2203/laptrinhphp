<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['branch_id', 'name', 'sku', 'category', 'icon', 'price', 'stock', 'active'])]

class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'integer', 'stock' => 'integer'];
    }
}
