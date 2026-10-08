<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameEvent> */
class GameEventFactory extends Factory
{
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'created_by' => User::factory(), 'title' => 'Giao lưu cuối tuần', 'type' => 'friendly', 'description' => 'Giao lưu cùng cộng đồng', 'format' => 'Giao lưu', 'skill_level' => 'Mọi trình độ', 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(12, 0), 'capacity' => 4, 'fee' => 0, 'status' => 'published'];
    }
}
