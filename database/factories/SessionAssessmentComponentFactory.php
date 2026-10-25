<?php

namespace Database\Factories;

use App\Enums\ComponentSource;
use App\Enums\LanguageAspect;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionAssessmentComponent>
 */
class SessionAssessmentComponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learning_session_id' => LearningSession::factory(),
            'name' => fake()->randomElement(['Speaking Practice', 'Role Play', 'Partisipasi']),
            'aspect' => LanguageAspect::Speaking,
            'weight' => 50,
            'max_score' => 100,
            'source' => ComponentSource::Manual,
        ];
    }
}
