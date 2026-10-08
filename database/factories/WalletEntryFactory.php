<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WalletEntry> */
class WalletEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'actor_id' => User::factory(), 'request_key' => (string) Str::uuid(), 'amount' => 10000, 'balance_after' => 10000, 'kind' => 'topup', 'description' => 'Nạp ví mẫu'];
    }
}
