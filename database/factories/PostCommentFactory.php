<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostComment> */
class PostCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['community_post_id' => CommunityPost::factory(), 'user_id' => User::factory(), 'content' => fake()->sentence()];
    }
}
