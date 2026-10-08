<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'received_by' => User::factory()->state(['role' => 'admin']), 'request_key' => (string) Str::uuid(), 'kind' => 'payment', 'method' => 'cash', 'amount' => 100000];
    }
}
