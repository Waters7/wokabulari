<?php

namespace App\Services\Reports;

use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\LearningSession;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\GradeService;
use App\Services\SessionScoreCalculator;
use Illuminate\Support\Collection;

/**
 * Rekap nilai peserta dalam rentang tanggal: rata-rata nilai sesi, tugas, quiz, penilaian periodik,
 * dan rata-rata gabungan (rata-rata dari sumber yang terisi). Semua skala 0–100.
 */
class ScoreAnalytics
{
    public const SOURCES = ['session' => 'Sesi', 'assignment' => 'Tugas', 'quiz' => 'Quiz', 'periodic' => 'Penilaian periodik'];

    public function __construct(protected SessionScoreCalculator $calculator, protected GradeService $grades) {}

    /**
     * @return Collection<int, array{user: User, session: ?float, assignment: ?float, quiz: ?float, periodic: ?float, overall: ?float, grade: ?string}>
     */
    public function perParticipant(ReportFilter $filter): Collection
    {
        $ids = $filter->participantIds();
        $from = $filter->from->toDateString();
        $to = $filter->to->toDateString();
        $values = array_fill_keys($ids, ['session' => [], 'assignment' => [], 'quiz' => [], 'periodic' => []]);

        // Nilai akhir sesi (komponen tertimbang), menurut tanggal sesi.
        LearningSession::query()->whereIn('classroom_id', $filter->classroomIds())->whereDateBetween('date', $from, $to)
            ->with(['components.scores' => fn ($q) => $q->whereIn('user_id', $ids)])
            ->get()->each(function (LearningSession $session) use (&$values) {
                foreach ($this->calculator->forSession($session, $session->components) as $userId => $score) {
                    if ($score !== null && isset($values[$userId])) {
                        $values[$userId]['session'][] = $score;
                    }
                }
            });

        // Tugas yang sudah dinilai, menurut waktu pengumpulan.
        AssignmentSubmission::query()->whereIn('user_id', $ids)->whereNotNull('final_score')
            ->whereBetween('submitted_at', [$filter->from->startOfDay(), $filter->to->endOfDay()])
            ->with('assignment:id,max_score')->get()
            ->each(function (AssignmentSubmission $submission) use (&$values) {
                if (($percentage = $submission->percentage()) !== null) {
                    $values[$submission->user_id]['assignment'][] = $percentage;
                }
            });

        // Quiz: skor terbaik per quiz, menurut waktu pengumpulan.
        QuizAttempt::query()->whereIn('user_id', $ids)->finished()->whereNotNull('percentage')
            ->whereBetween('submitted_at', [$filter->from->startOfDay(), $filter->to->endOfDay()])
            ->get(['user_id', 'quiz_id', 'percentage'])
            ->groupBy(fn (QuizAttempt $a) => $a->user_id.'-'.$a->quiz_id)
            ->each(function (Collection $attempts) use (&$values) {
                $values[$attempts->first()->user_id]['quiz'][] = (float) $attempts->max('percentage');
            });

        // Penilaian mingguan/bulanan yang sudah terbit, menurut awal periode.
        Assessment::query()->published()->whereIn('user_id', $ids)->whereNotNull('average')
            ->whereDateBetween('period_start', $from, $to)->get(['user_id', 'average'])
            ->each(function (Assessment $assessment) use (&$values) {
                $values[$assessment->user_id]['periodic'][] = (float) $assessment->average;
            });

        return $filter->participants()->map(function (User $user) use ($values) {
            $row = ['user' => $user];
            foreach (array_keys(self::SOURCES) as $source) {
                $row[$source] = GradeService::average($values[$user->id][$source] ?? []);
            }

            $row['overall'] = GradeService::average(array_map(fn ($s) => $row[$s], array_keys(self::SOURCES)));
            $row['grade'] = $this->grades->grade($row['overall']);

            return $row;
        })->values();
    }

    /**
     * Rata-rata gabungan per minggu (seluruh peserta dalam cakupan).
     *
     * @return Collection<string, ?float> tanggal awal minggu (dalam rentang) => rata-rata
     */
    public function weekly(ReportFilter $filter): Collection
    {
        return collect($filter->weeks())->mapWithKeys(fn ($week) => [
            // Kunci = awal minggu yang terpotong rentang, mis. Jumat 16 Okt bila rentang mulai di tengah minggu.
            $week->max($filter->from)->toDateString() => GradeService::average($this->perParticipant($filter->between($week->max($filter->from), $week->endOfWeek()->min($filter->to)))->pluck('overall')),
        ]);
    }
}
