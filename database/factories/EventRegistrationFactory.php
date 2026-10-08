<?php

namespace Database\Factories;

use App\Models\EventRegistration;
use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventRegistration> */
class EventRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return ['game_event_id' => GameEvent::factory(), 'user_id' => User::factory(), 'amount' => 0, 'paid_amount' => 0, 'method' => 'cash', 'status' => 'confirmed'];
    }
}
