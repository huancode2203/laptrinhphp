<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'product_id', 'name', 'quantity', 'unit_price', 'amount'])]

class SaleItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer', 'amount' => 'integer'];
    }
}
