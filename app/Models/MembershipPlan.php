<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'price', 'discount_percent', 'duration_days', 'active'])]

class MembershipPlan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['price' => 'integer', 'discount_percent' => 'integer', 'duration_days' => 'integer', 'active' => 'boolean'];
    }
}
