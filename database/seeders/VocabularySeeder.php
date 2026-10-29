<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\VocabularyWord;
use Illuminate\Database\Seeder;

/**
 * Bank kosakata awal (sementara Mandarin). Aman dijalankan ulang: kata yang sudah ada tidak ditimpa.
 */
class VocabularySeeder extends Seeder
{
    public function run(): void
    {
        $language = Language::query()->where('code', 'zh')->first();
        if (! $language) {
            return;
        }

        foreach (require database_path('vocabulary/zh.php') as $i => [$word, $reading, $meaning, $topic]) {
            VocabularyWord::query()->firstOrCreate(
                ['language_id' => $language->id, 'word' => $word, 'meaning' => $meaning],
                ['reading' => $reading, 'topic' => $topic, 'sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }
}
