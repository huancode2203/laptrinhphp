<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'branch_id', 'user_id', 'rating', 'content', 'visible', 'images', 'reply', 'replied_by'])]

class Review extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'rating' => 'integer', 'images' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
