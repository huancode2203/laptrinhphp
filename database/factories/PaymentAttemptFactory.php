<?php

namespace Database\Factories;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    public function definition(): array
    {
        return ['request_key' => (string) Str::uuid(), 'order_id' => 'BH-'.strtoupper(Str::random(20)), 'user_id' => User::factory(), 'purpose' => 'wallet', 'partner_code' => 'PAYOS', 'amount' => 100000, 'order_info' => 'Nap vi Badminton Hub', 'extra_data' => '', 'status' => 'pending', 'expires_at' => now()->addMinutes(15)];
    }
}
