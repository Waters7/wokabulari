<?php

namespace Database\Seeders;

use App\Enums\PointEvent;
use App\Models\Badge;
use App\Models\GamificationRule;
use App\Models\Level;
use Illuminate\Database\Seeder;

/**
 * Aturan poin, 5 level bertema bumbu woku, dan 10 lencana (SPEC 3.20). Aman dijalankan ulang (tidak menimpa ubahan admin).
 */
class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PointEvent::cases() as $event) {
            if ($event === PointEvent::Manual) {
                continue;
            }

            GamificationRule::query()->firstOrCreate(['event_key' => $event->value], [
                'name' => $event->label(),
                'description' => $event === PointEvent::QuizCompleted ? 'Skor% ÷ 5, maksimum sesuai poin aturan ini' : null,
                'points' => $event->defaultPoints(),
                'max_per_day' => match ($event) {
                    PointEvent::SessionCode => 4,
                    PointEvent::QuizCompleted => 5,
                    default => null,
                },
                'is_active' => $event->activeByDefault(),
            ]);
        }

        foreach ([
            ['Kemangi', 0, 'sparkles', 'green'],
            ['Serai', 300, 'fire', 'lime'],
            ['Jahe', 800, 'bolt', 'amber'],
            ['Kunyit', 1500, 'sun', 'yellow'],
            ['Woku Komplit', 2500, 'trophy', 'orange'],
        ] as [$name, $min, $icon, $color]) {
            Level::query()->firstOrCreate(['min_points' => $min], ['name' => $name, 'icon' => $icon, 'color' => $color]);
        }

        foreach ([
            ['langkah-pertama', 'Langkah Pertama', 'Kumpulkan 50 poin pertama.', 'rocket-launch', ['type' => 'points', 'min' => 50]],
            ['nyanda-absen-7-hari', 'Nyanda Absen 7 Hari', 'Hadir dan aktif 7 hari berturut-turut.', 'fire', ['type' => 'streak', 'days' => 7]],
            ['raja-quiz', 'Raja Quiz', 'Skor sempurna di 3 quiz.', 'trophy', ['type' => 'event_count', 'event' => 'quiz_perfect', 'count' => 3]],
            ['tugas-selalu-tepat-waktu', 'Tugas Selalu Tepat Waktu', '5 tugas dikumpulkan tepat waktu tanpa pernah terlambat.', 'clipboard-document-check', ['type' => 'event_count', 'event' => 'assignment_on_time', 'count' => 5, 'no_late_assignments' => true]],
            ['pande-speaking', 'Pande Speaking', 'Nilai berbicara ≥ 85 pada penilaian periodik.', 'microphone', ['type' => 'aspect', 'aspect' => 'speaking', 'min' => 85]],
            ['anak-asrama-teladan', 'Anak Asrama Teladan', 'Absen asrama tepat waktu 14 malam.', 'moon', ['type' => 'event_count', 'event' => 'dorm_attendance_on_time', 'count' => 14]],
            ['si-paling-rajin-isi-rating', 'Si Paling Rajin Isi Rating', 'Mengisi rating mingguan 4 minggu.', 'star', ['type' => 'event_count', 'event' => 'weekly_rating', 'count' => 4]],
            ['rajin-masuk-kelas', 'Rajin Masuk Kelas', 'Input kode sesi 20 kali.', 'qr-code', ['type' => 'event_count', 'event' => 'session_code', 'count' => 20]],
            ['bintang-pekan', 'Bintang Pekan Ini', 'Peserta terbaik di kelasnya dalam satu minggu.', 'star', ['type' => 'weekly_top']],
            ['woku-komplit', 'Woku Komplit', 'Mencapai level tertinggi (2.500 poin).', 'sparkles', ['type' => 'points', 'min' => 2500]],
        ] as [$slug, $name, $description, $icon, $criteria]) {
            Badge::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'icon' => $icon, 'criteria' => $criteria]);
        }
    }
}
