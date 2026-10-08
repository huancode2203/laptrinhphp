<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'direct_key', 'community_post_id'])]

class Conversation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')->withPivot('read_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
