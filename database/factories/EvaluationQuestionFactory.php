<?php

namespace Database\Factories;

use App\Enums\EvaluationAudience;
use App\Enums\EvaluationQuestionType;
use App\Models\EvaluationQuestion;
use App\Models\ProgramEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationQuestion>
 */
class EvaluationQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'program_evaluation_id' => ProgramEvaluation::factory(),
            'aspect' => 'Ruang kelas',
            'type' => EvaluationQuestionType::Likert,
            'body' => 'Kebersihan dan kenyamanan ruang kelas',
            'options' => null,
            'audience' => EvaluationAudience::All,
            'is_required' => true,
            'sort_order' => 0,
        ];
    }

    public function choice(): static
    {
        return $this->state(fn () => [
            'type' => EvaluationQuestionType::Choice,
            'body' => 'Bagaimana kecepatan Wi-Fi di lokasi kelas?',
            'options' => ['Cepat', 'Kadang lambat', 'Sering putus'],
        ]);
    }

    public function text(): static
    {
        return $this->state(fn () => ['type' => EvaluationQuestionType::Text, 'body' => 'Saran perbaikan fasilitas']);
    }

    public function forParticipants(): static
    {
        return $this->state(fn () => ['audience' => EvaluationAudience::Participant]);
    }
}
