<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['request_key', 'reference', 'branch_id', 'clerk_id', 'user_id', 'total_amount', 'method'])]

class Sale extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['total_amount' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
