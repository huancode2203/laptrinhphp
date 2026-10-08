<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_key', 'user_id', 'court_id', 'starts_at', 'ends_at', 'expires_at', 'released'])]

class CourtHold extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'released' => 'boolean'];
    }
}
