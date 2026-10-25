<?php

namespace Database\Factories;

use App\Enums\HealthReportStatus;
use App\Enums\HealthSeverity;
use App\Models\HealthReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthReport>
 */
class HealthReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->participant(),
            'symptoms' => ['fever', 'dizziness'],
            'description' => 'Badan panas sejak sore, kepala pusing.',
            'started_at' => now()->subHours(3),
            'temperature' => 38.2,
            'severity' => HealthSeverity::Moderate,
            'status' => HealthReportStatus::Reported,
            'reported_at' => now(),
        ];
    }

    public function emergency(): static
    {
        return $this->state(fn () => ['severity' => HealthSeverity::Emergency, 'is_emergency' => true]);
    }
}
