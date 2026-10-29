<?php

namespace App\Services\Evaluations;

use App\Enums\EvaluationAudience;
use App\Enums\EvaluationCategory;
use App\Enums\EvaluationQuestionType;

/**
 * Templat pertanyaan siap pakai; tim kerja tetap bisa mengubah atau menambah pertanyaan.
 */
class EvaluationTemplates
{
    /**
     * Pertanyaan templat dalam bentuk isian form editor.
     *
     * @return list<array{aspect: string, type: string, body: string, options: string, audience: string, is_required: bool}>
     */
    public static function questions(EvaluationCategory $category): array
    {
        $rows = match ($category) {
            EvaluationCategory::Facility => [
                ['Ruang kelas', 'likert', 'Kebersihan ruang kelas', 'all'],
                ['Ruang kelas', 'likert', 'Kenyamanan ruang kelas (suhu/AC, pencahayaan, kursi & meja)', 'all'],
                ['Peralatan belajar', 'likert', 'Kelengkapan dan fungsi peralatan (proyektor/TV, pengeras suara, papan tulis)', 'all'],
                ['Internet', 'likert', 'Kualitas Wi-Fi/internet di lokasi kelas', 'all'],
                ['Asrama', 'likert', 'Kebersihan dan kenyamanan kamar asrama', 'participant'],
                ['Asrama', 'likert', 'Kamar mandi dan ketersediaan air bersih', 'participant'],
                ['Asrama', 'likert', 'Keamanan lingkungan asrama', 'participant'],
                ['Ruang makan', 'likert', 'Kebersihan dan kenyamanan ruang makan', 'participant'],
                ['Fasilitas umum', 'likert', 'Tempat ibadah, toilet umum, dan area bersama', 'all'],
                ['Fasilitas pengajar', 'likert', 'Ruang kerja/istirahat dan perlengkapan untuk pengajar', 'teacher'],
                ['Prioritas', 'choice', 'Fasilitas yang paling perlu diperbaiki', 'all', ['Ruang kelas', 'Peralatan belajar', 'Internet/Wi-Fi', 'Asrama', 'Kamar mandi', 'Ruang makan', 'Fasilitas umum']],
                ['Saran', 'text', 'Saran perbaikan sarana dan prasarana', 'all', null, false],
            ],
            EvaluationCategory::Program => [
                ['Materi', 'likert', 'Materi pelajaran sesuai kebutuhan kerja/ujian', 'all'],
                ['Pengajar', 'likert', 'Pengajar menyampaikan materi dengan jelas', 'participant'],
                ['Metode', 'likert', 'Metode belajar membantu ngana cepat paham', 'participant'],
                ['Konsumsi', 'likert', 'Kualitas dan ketepatan waktu konsumsi', 'all'],
                ['Layanan tim kerja', 'likert', 'Tim kerja tanggap membantu kebutuhan', 'all'],
                ['Manajemen program', 'likert', 'Jadwal dan informasi program jelas', 'all'],
                ['Kesiapan', 'likert', 'Program membuat ngana lebih siap bekerja/berangkat', 'participant'],
                ['Saran', 'text', 'Saran untuk program berikutnya', 'all', null, false],
            ],
            EvaluationCategory::Other => [
                ['', 'likert', 'Penilaian umum', 'all'],
                ['Saran', 'text', 'Saran dan masukan', 'all', null, false],
            ],
        };

        return array_map(fn (array $row) => [
            'aspect' => $row[0],
            'type' => EvaluationQuestionType::from($row[1])->value,
            'body' => $row[2],
            'options' => implode("\n", $row[4] ?? []),
            'audience' => EvaluationAudience::from($row[3])->value,
            'is_required' => $row[5] ?? true,
        ], $rows);
    }
}
