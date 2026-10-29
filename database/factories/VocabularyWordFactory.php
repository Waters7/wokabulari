<?php

namespace Database\Factories;

use App\Models\Language;
use App\Models\VocabularyWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VocabularyWord>
 */
class VocabularyWordFactory extends Factory
{
    public function definition(): array
    {
        [$word, $reading, $meaning] = fake()->randomElement([['医生', 'yīshēng', 'Dokter'], ['护士', 'hùshi', 'Perawat'], ['谢谢', 'xièxie', 'Terima kasih']]);

        return [
            'language_id' => Language::factory(),
            'word' => $word,
            'reading' => $reading,
            'meaning' => $meaning,
            'topic' => 'Kesehatan',
            'sort_order' => fake()->numberBetween(1, 500),
            'is_active' => true,
        ];
    }
}
