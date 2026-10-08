<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_event_id', 'round', 'position', 'player_a', 'player_b', 'score_a', 'score_b', 'winner_id'])]

class TournamentMatch extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['round' => 'integer', 'position' => 'integer'];
    }

    public function a(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_a');
    }

    public function b(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_b');
    }
}
