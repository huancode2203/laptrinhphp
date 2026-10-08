<?php

namespace Database\Factories;

use App\Models\GameEvent;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TournamentMatch> */
class TournamentMatchFactory extends Factory
{
    public function definition(): array
    {
        return ['game_event_id' => GameEvent::factory(), 'round' => 1, 'position' => 0, 'player_a' => User::factory(), 'player_b' => User::factory()];
    }
}
