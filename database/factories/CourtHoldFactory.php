<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CourtHold> */
class CourtHoldFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'court_id' => Court::factory(), 'request_key' => (string) Str::uuid(), 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(11, 0), 'expires_at' => now()->addMinutes(5), 'released' => false];
    }
}
