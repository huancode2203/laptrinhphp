<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_key', 'user_id', 'court_id', 'starts_at', 'ends_at', 'expires_at', 'released'])]

class CourtHold extends Model
{
    use HasFactory;

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'released' => 'boolean'];
    }
}
