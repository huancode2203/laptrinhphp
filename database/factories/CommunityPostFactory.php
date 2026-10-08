<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommunityPost> */
class CommunityPostFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'branch_id' => Branch::factory(), 'title' => 'Tìm đồng đội', 'content' => 'Giao lưu cầu lông', 'type' => 'Tìm người đánh', 'skill_level' => 'Trung cấp', 'starts_at' => now()->addDay(), 'capacity' => 2, 'status' => 'open'];
    }
}
