<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\GameEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourtBlock> */
class CourtBlockFactory extends Factory
{
    public function definition(): array
    {
        return ['court_id' => Court::factory(), 'game_event_id' => GameEvent::factory(), 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(12, 0), 'active' => true];
    }
}
