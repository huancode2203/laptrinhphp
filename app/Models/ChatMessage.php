<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_key', 'conversation_id', 'user_id', 'content', 'deleted', 'attachments'])]

class ChatMessage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['deleted' => 'boolean', 'attachments' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
