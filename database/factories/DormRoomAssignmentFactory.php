<?php

namespace Database\Factories;

use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DormRoomAssignment>
 */
class DormRoomAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dorm_room_id' => DormRoom::factory(),
            'user_id' => User::factory()->participant(),
            'check_in_date' => now()->subWeek(),
            'check_out_date' => null,
        ];
    }
}
