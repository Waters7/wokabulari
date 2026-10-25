<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

/**
 * Analisis butir soal dan N-Gain pretest–posttest (SPEC 3.8).
 */
class QuizAnalytics
{
    /**
     * Tingkat kesukaran (proporsi skor) & daya pembeda (kelompok atas vs bawah 27%).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemAnalysis(Quiz $quiz): Collection
    {
        $attempts = $quiz->attempts()->where('status', AttemptStatus::Graded)->with('answers')->orderByDesc('percentage')->get();
        $questions = $quiz->questions()->get();

        if ($attempts->isEmpty()) {
            return collect();
        }

        $groupSize = max(1, (int) round($attempts->count() * 0.27));
        $upper = $attempts->take($groupSize);
        $lower = $attempts->reverse()->take($groupSize);

        $proportion = function (Collection $group, $question) use ($quiz) {
            $points = $quiz->pointsFor($question) ?: 1;
            $scores = $group->map(fn (QuizAttempt $a) => ($a->answers->firstWhere('question_id', $question->id)?->score ?? 0) / $points);

            return $scores->isEmpty() ? 0.0 : (float) $scores->avg();
        };

        return $questions->map(function ($question) use ($attempts, $upper, $lower, $proportion) {
            $p = round($proportion($attempts, $question), 2);
            $d = round($proportion($upper, $question) - $proportion($lower, $question), 2);

            return [
                'question' => $question,
                'difficulty' => $p,
                'difficulty_label' => $p > 0.7 ? 'Mudah' : ($p >= 0.3 ? 'Sedang' : 'Sukar'),
                'discrimination' => $d,
                'discrimination_label' => match (true) {
                    $d >= 0.4 => 'Sangat baik',
                    $d >= 0.3 => 'Baik',
                    $d >= 0.2 => 'Cukup',
                    default => 'Perlu diperbaiki',
                },
            ];
        });
    }

    /**
     * N-Gain per peserta = (post − pre) / (100 − pre). Kategori: tinggi ≥ 0,7; sedang 0,3–0,7; rendah < 0,3.
     *
     * @return array{pretest: Quiz|null, posttest: Quiz|null, rows: Collection<int, array<string, mixed>>, average: float|null, pre_avg: float|null, post_avg: float|null}
     */
    public function nGain(Classroom $classroom): array
    {
        $pretest = $this->latestOfType($classroom, QuizType::Pretest);
        $posttest = $this->latestOfType($classroom, QuizType::Posttest);

        $best = fn (?Quiz $quiz) => $quiz
            ? $quiz->attempts()->where('status', AttemptStatus::Graded)->selectRaw('user_id, max(percentage) as best')->groupBy('user_id')->pluck('best', 'user_id')
            : collect();

        $pre = $best($pretest);
        $post = $best($posttest);

        $rows = $classroom->participants()->orderBy('name')->get()->map(function ($user) use ($pre, $post) {
            $preScore = $pre->has($user->id) ? (float) $pre[$user->id] : null;
            $postScore = $post->has($user->id) ? (float) $post[$user->id] : null;
            $gain = self::gain($preScore, $postScore);

            return ['user' => $user, 'pre' => $preScore, 'post' => $postScore, 'gain' => $gain, 'category' => self::category($gain)];
        });

        $gains = $rows->pluck('gain')->filter(fn ($g) => $g !== null);

        return [
            'pretest' => $pretest,
            'posttest' => $posttest,
            'rows' => $rows,
            'average' => $gains->isEmpty() ? null : round((float) $gains->avg(), 2),
            'pre_avg' => GradeService::average($rows->pluck('pre')),
            'post_avg' => GradeService::average($rows->pluck('post')),
        ];
    }

    public static function gain(?float $pre, ?float $post): ?float
    {
        if ($pre === null || $post === null || $pre >= 100) {
            return null;
        }

        return round(($post - $pre) / (100 - $pre), 2);
    }

    public static function category(?float $gain): ?string
    {
        return match (true) {
            $gain === null => null,
            $gain >= 0.7 => 'Tinggi',
            $gain >= 0.3 => 'Sedang',
            default => 'Rendah',
        };
    }

    protected function latestOfType(Classroom $classroom, QuizType $type): ?Quiz
    {
        return Quiz::query()->where('classroom_id', $classroom->id)->where('type', $type)
            ->whereIn('status', [QuizStatus::Published, QuizStatus::Closed])
            ->latest('opens_at')->latest('id')->first();
    }
}
