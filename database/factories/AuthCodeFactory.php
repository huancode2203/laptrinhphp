<?php

namespace Database\Factories;

use App\Models\AuthCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<AuthCode> */
class AuthCodeFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'email' => fake()->safeEmail(), 'code_hash' => Hash::make('123456'), 'attempts' => 0, 'expires_at' => now()->addMinutes(10)];
    }
}
