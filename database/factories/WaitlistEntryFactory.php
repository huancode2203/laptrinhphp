<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WaitlistEntry> */
class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['court_id' => Court::factory(), 'user_id' => User::factory(), 'starts_at' => now()->addDay()->setTime(10, 0), 'ends_at' => now()->addDay()->setTime(11, 0), 'status' => 'waiting'];
    }
}
