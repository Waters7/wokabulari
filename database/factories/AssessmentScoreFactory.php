<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentAspect;
use App\Models\AssessmentScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScore>
 */
class AssessmentScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'assessment_aspect_id' => AssessmentAspect::factory(),
            'score' => fake()->numberBetween(60, 95),
        ];
    }
}
