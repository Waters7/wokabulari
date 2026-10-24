<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Enums\ReviewStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'date_from' => today(),
            'date_to' => today(),
            'type' => LeaveType::Excused,
            'reason' => 'Urusan keluarga',
            'status' => ReviewStatus::Pending,
        ];
    }

    public function sick(): static
    {
        return $this->state(fn () => ['type' => LeaveType::Sick, 'reason' => 'Demam']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => ReviewStatus::Approved, 'reviewed_at' => now()]);
    }
}
