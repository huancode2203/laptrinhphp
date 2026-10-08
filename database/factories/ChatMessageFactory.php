<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ChatMessage> */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['conversation_id' => Conversation::factory(), 'user_id' => User::factory(), 'request_key' => (string) Str::uuid(), 'content' => fake()->sentence(), 'deleted' => false];
    }
}
