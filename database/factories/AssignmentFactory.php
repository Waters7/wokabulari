<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\LanguageAspect;
use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'teacher_id' => fn (array $a) => Classroom::find($a['classroom_id'])?->teacher_id,
            'title' => 'Tugas '.fake()->words(3, true),
            'instructions' => 'Tulis 5 kalimat perkenalan diri dalam situasi kerja.',
            'submission_type' => SubmissionType::Mixed,
            'aspect' => LanguageAspect::Writing,
            'due_at' => now()->addDay(),
            'max_score' => 100,
            'allow_late' => true,
            'late_penalty_percent' => 20,
            'allow_resubmit' => false,
            'status' => AssignmentStatus::Published,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AssignmentStatus::Draft, 'published_at' => null]);
    }
}
