<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['request_key', 'user_id', 'actor_id', 'branch_id', 'amount', 'balance_after', 'kind', 'description', 'subject_type', 'subject_id'])]

class WalletEntry extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }
}
