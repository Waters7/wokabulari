<?php

namespace Database\Factories;

use App\Enums\DormAttendanceStatus;
use App\Models\DormAttendance;
use App\Models\Dormitory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DormAttendance>
 */
class DormAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'dormitory_id' => Dormitory::factory(),
            'date' => today(),
            'checked_in_at' => today()->setTime(20, 15),
            'status' => DormAttendanceStatus::PendingConfirmation,
            'attempts' => 1,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => DormAttendanceStatus::Confirmed, 'confirmed_at' => now()]);
    }
}
