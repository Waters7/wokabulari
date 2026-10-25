<?php

namespace Database\Factories;

use App\Enums\LanguageAspect;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\ReadingDisplay;
use App\Enums\ResultVisibility;
use App\Models\Classroom;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'teacher_id' => fn (array $a) => Classroom::find($a['classroom_id'])?->teacher_id,
            'type' => QuizType::Quiz,
            'title' => 'Quiz '.fake()->words(2, true),
            'aspect' => LanguageAspect::Vocabulary,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'passing_score' => 70,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'result_visibility' => ResultVisibility::Immediately,
            'reading_display' => ReadingDisplay::Ruby,
            'score_scale' => 100,
            'status' => QuizStatus::Published,
        ];
    }

    public function type(QuizType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
