<?php

namespace Database\Factories;

use App\Models\EvaluationAnswer;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationAnswer>
 */
class EvaluationAnswerFactory extends Factory
{
    public function definition(): array
    {
        $score = fake()->numberBetween(1, 5);

        return [
            'evaluation_response_id' => EvaluationResponse::factory(),
            'evaluation_question_id' => EvaluationQuestion::factory(),
            'value' => (string) $score,
            'score' => $score,
        ];
    }
}
