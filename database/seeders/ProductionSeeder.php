<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * Data dasar produksi: role, permission, bahasa, aturan gamifikasi, level, lencana.
 * Tidak berisi data contoh.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        foreach ([
            ['code' => 'en', 'name' => 'Inggris', 'exam_format' => 'IELTS/TOEIC', 'reading_label' => 'Pelafalan'],
            ['code' => 'zh', 'name' => 'Mandarin', 'exam_format' => 'HSK', 'reading_label' => 'Pinyin'],
            ['code' => 'ja', 'name' => 'Jepang', 'exam_format' => 'JFT-Basic/JLPT', 'reading_label' => 'Romaji'],
            ['code' => 'de', 'name' => 'Jerman', 'exam_format' => 'Goethe-Zertifikat', 'reading_label' => 'Pelafalan'],
        ] as $language) {
            Language::query()->updateOrCreate(['code' => $language['code']], $language);
        }

        foreach (['GamificationSeeder', 'VocabularySeeder'] as $seeder) {
            if (class_exists($class = __NAMESPACE__.'\\'.$seeder)) {
                $this->call($class);
            }
        }
    }
}
