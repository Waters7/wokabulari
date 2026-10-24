<?php

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->unique()->country(),
            'exam_format' => 'TOEIC',
            'reading_label' => null,
        ];
    }

    public function english(): static
    {
        return $this->state(fn () => ['code' => 'en', 'name' => 'Inggris', 'exam_format' => 'IELTS/TOEIC', 'reading_label' => 'Pelafalan']);
    }

    public function mandarin(): static
    {
        return $this->state(fn () => ['code' => 'zh', 'name' => 'Mandarin', 'exam_format' => 'HSK', 'reading_label' => 'Pinyin']);
    }
}
