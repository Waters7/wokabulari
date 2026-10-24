<?php

namespace Database\Seeders\Demo;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveType;
use App\Enums\MaterialType;
use App\Enums\MaterialVisibility;
use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\SessionAttendance;
use App\Models\SessionMaterial;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Data contoh Fase 2–3: absen harian & sesi 2 minggu, materi, dan izin.
 */
class LearningDemoSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    protected array $topics = [
        'en' => ['Introducing Yourself at Work', 'Talking with Patients', 'Hospital Vocabulary', 'Giving Instructions', 'Taking Vital Signs', 'Telephone English', 'Describing Symptoms', 'Shift Handover', 'Medication Times', 'Job Interview Practice'],
        'zh' => ['自我介绍 Perkenalan', '数字和时间 Angka & waktu', '医院 Rumah sakit', '身体 Bagian tubuh', '问路 Menanyakan arah', '打电话 Menelepon', '看病 Berobat', '家庭 Keluarga', '工作 Pekerjaan', 'HSK 1 Latihan'],
        'ja' => ['自己紹介 Jikoshoukai', '数字と時間', '病院の言葉', '体の部分', '介護の基本', '電話の会話', '症状を伝える', '申し送り', '薬の時間', '面接練習'],
        'de' => ['Sich vorstellen', 'Zahlen und Uhrzeit', 'Im Krankenhaus', 'Der Körper', 'Termine vereinbaren', 'Am Telefon', 'Symptome beschreiben', 'Dienstübergabe', 'Medikamente', 'Vorstellungsgespräch'],
    ];

    public function run(): void
    {
        $batch = Batch::current();
        if (! $batch) {
            return;
        }

        // 10 hari kerja terakhir (riwayat) + hari ini + 10 hari kerja berikutnya (jadwal).
        $days = collect(range(-16, 16))
            ->map(fn ($i) => today()->addDays($i))
            ->reject(fn (CarbonInterface $d) => $d->isWeekend())
            ->filter(fn (CarbonInterface $d) => $d->isToday() || abs(today()->diffInWeekdays($d, false)) <= 10)
            ->values();

        foreach (Classroom::query()->where('batch_id', $batch->id)->with(['language', 'participants'])->get() as $classroom) {
            $topics = $this->topics[$classroom->language->code] ?? $this->topics['en'];

            foreach ($days as $index => $day) {
                $isPast = $day->lt(today());
                $isToday = $day->isToday();

                $session = LearningSession::query()->firstOrCreate(
                    ['classroom_id' => $classroom->id, 'date' => $day->toDateString(), 'start_time' => '08:30'],
                    [
                        'teacher_id' => $classroom->teacher_id,
                        'end_time' => '10:30',
                        'topic' => $topics[$index % count($topics)],
                        'objectives' => 'Peserta mampu menggunakan kosakata dan ungkapan topik ini dalam situasi kerja.',
                        'status' => $isPast ? SessionStatus::Completed : SessionStatus::Scheduled,
                        'started_at' => $isPast ? $day->copy()->setTime(8, 32) : null,
                        'ended_at' => $isPast ? $day->copy()->setTime(10, 30) : null,
                    ],
                );

                if ($index < 4 && $session->materials()->doesntExist()) {
                    SessionMaterial::query()->create([
                        'learning_session_id' => $session->id,
                        'teacher_id' => $classroom->teacher_id,
                        'language_id' => $classroom->language_id,
                        'title' => 'Video: '.$session->topic,
                        'description' => 'Tonton sebelum kelas dan catat kosakata baru.',
                        'level' => $classroom->level,
                        'topic' => $session->topic,
                        'type' => MaterialType::Link,
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'visibility' => MaterialVisibility::Immediate,
                        'is_downloadable' => false,
                    ]);
                }

                if (! $isPast) {
                    continue;
                }

                foreach ($classroom->participants as $pIndex => $participant) {
                    // Variasi status: sebagian kecil terlambat, izin, sakit, atau alpa.
                    $roll = ($pIndex * 7 + $index * 3) % 20;
                    $status = match (true) {
                        $roll === 0 => AttendanceStatus::Absent,
                        $roll === 1 => AttendanceStatus::Sick,
                        $roll === 2 => AttendanceStatus::Excused,
                        $roll <= 5 => AttendanceStatus::Late,
                        default => AttendanceStatus::Present,
                    };

                    DailyAttendance::query()->firstOrCreate(
                        ['user_id' => $participant->id, 'date' => $day->toDateString()],
                        [
                            'checked_in_at' => $status->countsAsAttended() ? $day->copy()->setTime($status === AttendanceStatus::Late ? 7 : 7, $status === AttendanceStatus::Late ? 48 : 5 + $pIndex) : null,
                            'face_distance' => $status->countsAsAttended() ? 0.28 + ($pIndex % 5) / 50 : null,
                            'latitude' => $status->countsAsAttended() ? 1.4605 : null,
                            'longitude' => $status->countsAsAttended() ? 124.8228 : null,
                            'accuracy_m' => $status->countsAsAttended() ? 12 : null,
                            'distance_m' => $status->countsAsAttended() ? 18 + $pIndex : null,
                            'status' => $status,
                            'approval_note' => in_array($status, [AttendanceStatus::Sick, AttendanceStatus::Excused], true) ? 'Izin disetujui' : null,
                        ],
                    );

                    SessionAttendance::query()->firstOrCreate(
                        ['learning_session_id' => $session->id, 'user_id' => $participant->id],
                        [
                            'status' => $status === AttendanceStatus::Late ? AttendanceStatus::Present : $status,
                            'submitted_at' => $status->countsAsAttended() ? $day->copy()->setTime(8, 40) : null,
                            'marked_by' => $status->countsAsAttended() ? null : $classroom->teacher_id,
                            'note' => $status->countsAsAttended() ? null : 'Otomatis saat sesi selesai',
                        ],
                    );
                }
            }

            // Satu pengajuan izin menunggu per kelas.
            $requester = $classroom->participants->get(2);
            if ($requester && $requester->leaveRequests()->doesntExist()) {
                LeaveRequest::query()->create([
                    'user_id' => $requester->id,
                    'date_from' => today()->addDays(2)->toDateString(),
                    'date_to' => today()->addDays(2)->toDateString(),
                    'type' => LeaveType::Excused,
                    'reason' => 'Mengurus dokumen paspor di kantor imigrasi',
                    'status' => ReviewStatus::Pending,
                ]);
            }
        }
    }
}
