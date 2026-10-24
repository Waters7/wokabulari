<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\DailyAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyAttendance>
 */
class DailyAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'date' => today(),
            'checked_in_at' => now()->setTime(7, 10),
            'face_distance' => 0.32,
            'latitude' => 1.4605,
            'longitude' => 124.8228,
            'accuracy_m' => 12,
            'distance_m' => 20,
            'status' => AttendanceStatus::Present,
        ];
    }

    public function rejected(string $reason = 'Di luar radius lokasi kelas'): static
    {
        return $this->state(fn () => ['status' => AttendanceStatus::Rejected, 'reject_reason' => $reason]);
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
