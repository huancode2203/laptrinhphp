<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'email', 'code_hash', 'reset_hash', 'attempts', 'expires_at', 'verified_at', 'used_at', 'demo'])]
#[Hidden(['code_hash', 'reset_hash'])]
class AuthCode extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'used_at' => 'immutable_datetime', 'demo' => 'boolean'];
    }
}
