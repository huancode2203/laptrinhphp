<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'branch_id', 'kind', 'value', 'minimum_amount', 'max_discount', 'usage_limit', 'starts_at', 'expires_at', 'active'])]

class Coupon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'active' => 'boolean'];
    }
}
