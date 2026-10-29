<?php

namespace Database\Factories;

use App\Models\EvaluationResponse;
use App\Models\ProgramEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationResponse>
 */
class EvaluationResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_evaluation_id' => ProgramEvaluation::factory(),
            'user_id' => null,
            'respondent_hash' => hash('sha256', fake()->unique()->uuid()),
            'respondent_role' => ProgramEvaluation::ROLE_PARTICIPANT,
            'batch_id' => null,
            'submitted_at' => now(),
        ];
    }
}
