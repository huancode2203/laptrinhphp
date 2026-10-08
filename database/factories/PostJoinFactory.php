<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\PostJoin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostJoin> */
class PostJoinFactory extends Factory
{
    public function definition(): array
    {
        return ['community_post_id' => CommunityPost::factory(), 'user_id' => User::factory(), 'status' => 'pending'];
    }
}
