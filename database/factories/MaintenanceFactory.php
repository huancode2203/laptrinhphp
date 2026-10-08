<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Maintenance> */
class MaintenanceFactory extends Factory
{
    public function definition(): array
    {
        return ['court_id' => Court::factory(), 'starts_at' => now()->addDay()->startOfDay()->addHours(10), 'ends_at' => now()->addDay()->startOfDay()->addHours(11), 'reason' => 'Bảo trì sàn sân', 'status' => 'scheduled'];
    }
}
