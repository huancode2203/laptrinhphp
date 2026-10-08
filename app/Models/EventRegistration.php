<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_event_id', 'user_id', 'amount', 'paid_amount', 'method', 'status', 'created_at'])]

class EventRegistration extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_amount' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'game_event_id');
    }
}
