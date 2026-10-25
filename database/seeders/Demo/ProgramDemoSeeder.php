<?php

namespace Database\Seeders\Demo;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Dormitory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh kegiatan program (Fase 11): pengumuman dari Tim Kerja, Pengajar, dan Asrama.
 */
class ProgramDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Announcement::query()->exists()) {
            return;
        }

        $user = fn (string $email) => User::query()->where('email', $email)->first();
        $classroom = fn (string $name) => Classroom::query()->where('name', $name)->first();
        $female = Dormitory::query()->where('name', 'like', '%Putri%')->first();

        $items = [
            [$user('program@wokabulari.test'), AnnouncementSource::Team, AnnouncementAudience::All, null, null, true, 5,
                'Selamat datang di Program SMK Go Global', "Selamat bergabung! Program kelas bahasa berlangsung 12 minggu. Absen harian dibuka pukul 05.00 dan dianggap terlambat setelah 07.30 WITA.\n\nTetap semangat, torang go global!"],
            [$user('program@wokabulari.test'), AnnouncementSource::Team, AnnouncementAudience::Role, null, Role::Guardian, false, 3,
                'Portal wali sudah aktif', 'Bapak/Ibu dapat memantau kehadiran, nilai, izin keluar, dan kabar kesehatan anak melalui portal wali. Hubungi panitia bila belum menerima kode undangan.'],
            [$user('konsumsi@wokabulari.test'), AnnouncementSource::Team, AnnouncementAudience::All, null, null, false, 1,
                'Menu spesial Jumat: ikan woku belanga', 'Jumat siang ada menu spesial ikan woku belanga dari Dapur Woku Tinoor. Jangan lupa tanda tangan penerimaan di aplikasi, neh!'],
            [$user('pengajar.en@wokabulari.test'), AnnouncementSource::Teacher, AnnouncementAudience::Classroom, $classroom('Kelas Inggris A')?->id, null, false, 2,
                'Tugas speaking dikumpulkan Jumat', "Rekam perkenalan diri 1 menit dalam bahasa Inggris dan kumpulkan lewat menu Tugas paling lambat Jumat 21.00 WITA.\nGood luck!"],
            [$user('pengajar.zh@wokabulari.test'), AnnouncementSource::Teacher, AnnouncementAudience::Classroom, $classroom('Kelas Mandarin A')?->id, null, true, 0,
                'Latihan nada sebelum tryout HSK 1', 'Besok sebelum sesi, kita latihan nada 1–4 selama 15 menit. Bawa catatan pinyin masing-masing ya. 加油!'],
            [$user('asrama@wokabulari.test'), AnnouncementSource::Dormitory, AnnouncementAudience::Dormitory, $female?->id, null, true, 1,
                'Jadwal piket kebersihan kamar', "Senin–Rabu: kamar A101–A102\nKamis–Sabtu: kamar A103–A104\nPiket dimulai 06.00 WITA. Laporkan perlengkapan yang kurang lewat menu Keluhan Asrama."],
            [$user('asrama@wokabulari.test'), AnnouncementSource::Dormitory, AnnouncementAudience::All, null, null, false, 4,
                'Kerja bakti Sabtu pagi', 'Sabtu pukul 07.00 WITA seluruh penghuni asrama kerja bakti membersihkan halaman. Absen asrama malam tetap seperti biasa.'],
        ];

        foreach ($items as [$creator, $source, $audience, $targetId, $role, $pinned, $daysAgo, $title, $body]) {
            if (! $creator || ($audience === AnnouncementAudience::Classroom && ! $targetId) || ($audience === AnnouncementAudience::Dormitory && ! $targetId)) {
                continue;
            }

            Announcement::query()->create([
                'title' => $title, 'body' => $body, 'source' => $source, 'audience' => $audience,
                'target_id' => $targetId, 'target_role' => $role, 'is_pinned' => $pinned,
                'published_at' => now()->subDays($daysAgo)->setTime(8, 0), 'notified_at' => now()->subDays($daysAgo),
                'created_by' => $creator->id,
            ]);
        }
    }
}
