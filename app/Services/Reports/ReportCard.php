<?php

namespace App\Services\Reports;

use App\Enums\AssignmentStatus;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rapor peserta: akumulasi semua penilaian di satu kelas selama angkatan berjalan (sampai hari ini).
 * Nilai akhir memakai rumus Rekap nilai Pusat Laporan: rata-rata dari sesi, tugas, quiz, dan penilaian periodik.
 */
class ReportCard
{
    public function __construct(
        protected AttendanceAnalytics $attendance,
        protected ScoreAnalytics $scores,
        protected QuizCombination $combiner,
    ) {}

    /** Rentang rapor: awal angkatan s.d. akhir angkatan (paling lambat hari ini). */
    public function filter(Classroom $classroom): ReportFilter
    {
        $batch = $classroom->batch;
        $from = $batch?->start_date ?? CarbonImmutable::parse($classroom->created_at)->startOfDay();
        $to = $batch?->end_date && $batch->end_date->lt(today()) ? $batch->end_date : today();

        return ReportFilter::make($from, $to, $batch?->id, $classroom->id);
    }

    /**
     * Ringkasan semua peserta kelas (untuk daftar & Excel).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(Classroom $classroom): Collection
    {
        $filter = $this->filter($classroom);
        $attendance = $this->attendance->daily($filter)['participants']->keyBy(fn ($row) => $row['user']->id);

        return $this->scores->perParticipant($filter)->map(fn (array $row) => $row + [
            'attendance' => $attendance->get($row['user']->id)['rate'] ?? null,
        ]);
    }

    /**
     * Rapor lengkap satu peserta.
     *
     * @return array<string, mixed>
     */
    public function for(Classroom $classroom, User $user): array
    {
        $filter = $this->filter($classroom)->forUser($user->id);
        $daily = $this->attendance->daily($filter);
        $sessions = $this->attendance->sessions($filter);
        $sessionExpected = (int) $sessions->sum('expected');
        $scores = $this->scores->perParticipant($filter)->first();

        $quizzes = Quiz::query()->where('classroom_id', $classroom->id)->where('status', '!=', QuizStatus::Draft)
            ->orderByRaw('opens_at is null')->orderBy('opens_at')->orderBy('id')->get();
        $attempts = QuizAttempt::query()->whereIn('quiz_id', $quizzes->pluck('id'))->where('user_id', $user->id)->finished()->get()->groupBy('quiz_id');

        $assignments = Assignment::query()->where('classroom_id', $classroom->id)->where('status', '!=', AssignmentStatus::Draft)
            ->with(['submissions' => fn ($q) => $q->where('user_id', $user->id)])->orderBy('due_at')->get();

        // Pretest/posttest bisa dipecah beberapa quiz (pilihan ganda + esai): gabungkan jumlah poinnya.
        $combined = function (QuizType $type) use ($quizzes, $classroom, $user) {
            $set = $quizzes->where('type', $type)->values();
            $row = $set->isEmpty() ? null : $this->combiner->combine($classroom, $set)['rows']->first(fn ($r) => $r['user']->id === $user->id);

            return ['quizzes' => $set, 'row' => $row && $row['missing'] < $set->count() ? $row : null];
        };
        $pre = $combined(QuizType::Pretest);
        $post = $combined(QuizType::Posttest);
        $gain = QuizAnalytics::gain($pre['row']['percentage'] ?? null, $post['row']['percentage'] ?? null);

        return [
            'user' => $user->loadMissing('profile'),
            'classroom' => $classroom->loadMissing(['batch', 'language', 'teacher']),
            'from' => $filter->from,
            'to' => $filter->to,
            'scores' => $scores,
            'attendance' => ['rate' => $daily['rate'], 'totals' => $daily['totals'], 'expected' => $daily['expected']],
            'sessionAttendance' => ['rate' => $sessionExpected > 0 ? round($sessions->sum('attended') / $sessionExpected * 100, 1) : null, 'count' => $sessions->count()],
            'quizzes' => $quizzes->map(function (Quiz $quiz) use ($attempts) {
                $best = ($attempts->get($quiz->id) ?? collect())->sortByDesc(fn ($a) => (float) $a->score)->first();

                return ['quiz' => $quiz, 'attempt' => $best];
            }),
            'assignments' => $assignments->map(fn (Assignment $assignment) => ['assignment' => $assignment, 'submission' => $assignment->submissions->first()]),
            'nGain' => [
                'pretest' => $pre['quizzes'],
                'posttest' => $post['quizzes'],
                'row' => [
                    'pre' => $pre['row']['percentage'] ?? null,
                    'post' => $post['row']['percentage'] ?? null,
                    'gain' => $gain,
                    'category' => QuizAnalytics::category($gain),
                    'pending' => ($pre['row']['pending'] ?? 0) + ($post['row']['pending'] ?? 0),
                ],
            ],
            'periodic' => Assessment::query()->published()->where('classroom_id', $classroom->id)->where('user_id', $user->id)
                ->with('scores.aspect')->orderBy('period_start')->get(),
        ];
    }
}
