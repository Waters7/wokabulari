<?php

namespace App\Services\Reports;

use App\Enums\AttemptStatus;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Support\Collection;

/**
 * Gabungan beberapa quiz (mis. pretest pilihan ganda + pretest esai) menjadi satu nilai per peserta.
 * Nilai akhir = jumlah poin semua bagian ÷ jumlah poin maksimal × 100. Bagian yang tidak dikerjakan
 * bernilai 0; bagian yang esainya belum selesai dinilai memakai nilai sementara.
 */
class QuizCombination
{
    public function __construct(protected GradeService $grades) {}

    /**
     * @param  Collection<int, Quiz>  $quizzes
     * @return array{rows: Collection<int, array<string, mixed>>, average: ?float, highest: ?float, lowest: ?float, complete: int}
     */
    public function combine(Classroom $classroom, Collection $quizzes): array
    {
        $quizzes = $quizzes->values()->each->loadMissing('questions');
        $maxPoints = $quizzes->mapWithKeys(fn (Quiz $quiz) => [$quiz->id => $quiz->totalPoints()]);

        // Attempt terbaik (skor tertinggi) per peserta per quiz, dari attempt yang sudah dikumpulkan.
        $best = QuizAttempt::query()->whereIn('quiz_id', $quizzes->pluck('id'))->finished()->get()
            ->groupBy(fn (QuizAttempt $a) => $a->user_id.'-'.$a->quiz_id)
            ->map(fn (Collection $attempts) => $attempts->sortByDesc(fn (QuizAttempt $a) => (float) $a->score)->first());

        $rows = $classroom->participants()->active()->orderBy('name')->get()->map(function (User $user) use ($quizzes, $maxPoints, $best) {
            $parts = [];
            $score = 0.0;
            $max = 0.0;
            $pending = 0;
            $missing = 0;

            foreach ($quizzes as $quiz) {
                $attempt = $best->get($user->id.'-'.$quiz->id);
                $status = match (true) {
                    ! $attempt => 'missing',
                    $attempt->status === AttemptStatus::NeedsGrading => 'pending',
                    default => 'done',
                };
                $pending += $status === 'pending' ? 1 : 0;
                $missing += $status === 'missing' ? 1 : 0;

                $parts[$quiz->id] = ['score' => $attempt ? round((float) $attempt->score, 2) : null, 'max' => (float) $maxPoints[$quiz->id], 'status' => $status];
                $score += (float) ($attempt?->score ?? 0);
                $max += (float) $maxPoints[$quiz->id];
            }

            $percentage = $max > 0 ? round($score / $max * 100, 2) : null;

            return [
                'user' => $user,
                'parts' => $parts,
                'score' => round($score, 2),
                'max' => round($max, 2),
                'percentage' => $percentage,
                'grade' => $this->grades->grade($percentage),
                'pending' => $pending,
                'missing' => $missing,
            ];
        });

        // Peringkat: nilai sama = peringkat sama (1, 2, 2, 4).
        $sorted = $rows->sortByDesc(fn ($r) => $r['percentage'] ?? -1)->values();
        $rank = 0;
        $previous = null;
        $sorted = $sorted->map(function (array $row, int $i) use (&$rank, &$previous) {
            if ($row['percentage'] !== $previous) {
                $rank = $i + 1;
                $previous = $row['percentage'];
            }

            return $row + ['rank' => $rank];
        });

        $scored = $sorted->filter(fn ($r) => $r['missing'] < count($r['parts']))->pluck('percentage');

        return [
            'rows' => $sorted,
            'average' => GradeService::average($scored),
            'highest' => $scored->max(),
            'lowest' => $scored->min(),
            'complete' => $sorted->where('missing', 0)->where('pending', 0)->count(),
        ];
    }
}
