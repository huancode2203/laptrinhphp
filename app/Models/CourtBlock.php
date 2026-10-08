<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['court_id', 'game_event_id', 'starts_at', 'ends_at', 'active'])]
class CourtBlock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'active' => 'boolean'];
    }
}
