<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => 'BH-'.strtoupper(Str::random(10)), 'request_key' => (string) Str::uuid(), 'user_id' => User::factory(), 'court_id' => Court::factory(), 'starts_at' => now()->addDay()->startOfDay()->addHours(10), 'ends_at' => now()->addDay()->startOfDay()->addHours(11), 'status' => 'confirmed', 'court_amount' => 100000, 'service_amount' => 0, 'total_amount' => 100000, 'paid_amount' => 0];
    }
}
