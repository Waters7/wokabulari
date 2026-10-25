<?php

namespace Database\Factories;

use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfWeek();

        return [
            'classroom_id' => Classroom::factory(),
            'user_id' => User::factory()->participant(),
            'period_type' => PeriodType::Weekly,
            'period_start' => $start,
            'period_end' => $start->endOfWeek(),
            'listening' => 80, 'speaking' => 75, 'reading' => 82, 'writing' => 70,
            'vocabulary' => 85, 'grammar' => 72, 'attitude' => 90,
            'average' => 79.14,
            'grade' => 'B',
            'status' => PublishStatus::Draft,
            'visible_to_guardian' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published, 'published_at' => now()]);
    }
}
