<?php

namespace Database\Seeders\Demo;

use App\Actions\Assignments\SaveAssignment;
use App\Enums\AssignmentStatus;
use App\Enums\LanguageAspect;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Classroom;
use Illuminate\Database\Seeder;

/**
 * Data contoh Fase 5: 5 tugas (lintas kelas) beserta pengumpulan dan nilai.
 */
class AssignmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $batch = Batch::current();
        if (! $batch || Assignment::query()->exists()) {
            return;
        }

        $templates = [
            ['Perkenalan diri di tempat kerja', 'Rekam perkenalan diri 1 menit: nama, asal, pendidikan, alasan ingin bekerja di luar negeri.', SubmissionType::Audio, LanguageAspect::Speaking, -5],
            ['Kosakata ruang perawatan', 'Tulis 15 kosakata benda di ruang perawatan beserta artinya. Foto hasil tulisan tangan.', SubmissionType::Photo, LanguageAspect::Vocabulary, -3],
            ['Dialog dengan pasien', 'Tulis dialog singkat (8–10 baris) antara perawat dan pasien yang baru masuk.', SubmissionType::Text, LanguageAspect::Writing, -1],
            ['Membaca jadwal obat', 'Baca teks terlampir lalu jawab 5 pertanyaan. Boleh unggah berkas atau tulis langsung.', SubmissionType::Mixed, LanguageAspect::Reading, 1],
            ['Latihan percakapan telepon', 'Rekam percakapan telepon (peran perawat) menerima panggilan dari keluarga pasien.', SubmissionType::Audio, LanguageAspect::Speaking, 3],
        ];

        $save = app(SaveAssignment::class);

        foreach (Classroom::query()->where('batch_id', $batch->id)->with(['participants', 'teacher'])->get() as $cIndex => $classroom) {
            foreach ($templates as $tIndex => [$title, $instructions, $type, $aspect, $dayOffset]) {
                // Tiap kelas mendapat 2 tugas agar total ±8 tugas; tugas pertama ditautkan ke sesi.
                if (($tIndex + $cIndex) % 3 !== 0 && $tIndex !== 2) {
                    continue;
                }

                $due = now()->addDays($dayOffset)->setTime(21, 0);
                $session = $classroom->sessions()->whereDate('date', '<=', $due)->orderByDesc('date')->first();

                $assignment = $save->save($classroom, $classroom->teacher, [
                    'learning_session_id' => $tIndex === 2 ? $session?->id : null,
                    'title' => $title,
                    'instructions' => $instructions,
                    'submission_type' => $type->value,
                    'aspect' => $aspect->value,
                    'due_at' => $due,
                    'max_score' => 100,
                    'allow_late' => true,
                    'late_penalty_percent' => 10,
                    'allow_resubmit' => $type === SubmissionType::Text,
                ]);
                $assignment->update(['status' => AssignmentStatus::Published, 'published_at' => $due->copy()->subDays(3)]);

                if ($dayOffset > 0) {
                    continue;
                }

                foreach ($classroom->participants as $pIndex => $participant) {
                    if ($pIndex === 7) {
                        continue; // satu peserta belum mengumpulkan
                    }

                    $late = $pIndex === 6;
                    $graded = $pIndex < 5;
                    $score = $graded ? 70 + (($pIndex * 7 + $tIndex * 3) % 28) : null;

                    AssignmentSubmission::query()->create([
                        'assignment_id' => $assignment->id,
                        'user_id' => $participant->id,
                        'text_answer' => $type->allowsText() ? 'Contoh jawaban peserta untuk tugas "'.$title.'".' : null,
                        'submitted_at' => $late ? $due->copy()->addHours(3) : $due->copy()->subHours(5 + $pIndex),
                        'is_late' => $late,
                        'score' => $score,
                        'final_score' => $score !== null ? $assignment->finalScore($score, $late) : null,
                        'feedback' => $graded ? 'Bagus! Perhatikan ejaan dan tanda baca.' : null,
                        'status' => $graded ? SubmissionStatus::Graded : ($late ? SubmissionStatus::Late : SubmissionStatus::Submitted),
                        'graded_by' => $graded ? $classroom->teacher_id : null,
                        'graded_at' => $graded ? $due->copy()->addDay() : null,
                    ]);
                }

                $save->syncComponent($assignment);
            }
        }
    }
}
