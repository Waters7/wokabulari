<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Models\DormInventory;
use App\Models\DormRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DormInventory>
 */
class DormInventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dorm_room_id' => DormRoom::factory(),
            'item_name' => fake()->randomElement(['Tempat tidur', 'Kasur', 'Lemari', 'Kipas angin', 'Meja belajar']),
            'quantity' => fake()->numberBetween(1, 4),
            'condition' => ItemCondition::Good,
        ];
    }
}
