<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBank>
 */
class QuestionBankFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_id' => null,
            'language_id' => Language::factory(),
            'name' => 'Bank '.fake()->words(2, true),
            'level' => 'Dasar',
            'topic' => 'Rumah sakit',
        ];
    }
}
