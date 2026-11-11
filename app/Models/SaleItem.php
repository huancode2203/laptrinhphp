<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'product_id', 'name', 'quantity', 'unit_price', 'amount', 'unit_cost', 'returned_quantity'])]
#[Hidden(['unit_cost'])]

class SaleItem extends Model
{
    use HasFactory;

    protected $appends = ['net_quantity', 'net_amount'];

    public function getNetQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }

    public function getNetAmountAttribute(): int
    {
        return $this->net_quantity * $this->unit_price;
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer', 'amount' => 'integer', 'unit_cost' => 'integer', 'returned_quantity' => 'integer'];
    }
}
