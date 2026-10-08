<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'branch_id' => Branch::factory(), 'user_id' => User::factory(), 'rating' => 5, 'content' => 'Sân sạch, chơi tốt', 'visible' => true];
    }
}
