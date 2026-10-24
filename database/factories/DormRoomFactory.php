<?php

namespace Database\Factories;

use App\Models\Dormitory;
use App\Models\DormRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DormRoom>
 */
class DormRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dormitory_id' => Dormitory::factory(),
            'number' => (string) fake()->unique()->numberBetween(101, 999),
            'floor' => '1',
            'capacity' => 4,
        ];
    }
}
