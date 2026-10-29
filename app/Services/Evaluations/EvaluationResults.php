<?php

namespace App\Services\Evaluations;

use App\Enums\EvaluationQuestionType;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationQuestion;
use App\Models\ProgramEvaluation;
use Illuminate\Support\Collection;

/**
 * Rekap hasil survei: tingkat partisipasi, Indeks Kepuasan 0–100, rata-rata per aspek, dan sebaran jawaban.
 */
class EvaluationResults
{
    /**
     * @return array{
     *     target: int, responded: int, rate: ?float,
     *     roles: array<string, array{target: int, responded: int}>,
     *     index: ?float,
     *     aspects: list<array{aspect: string, average: float, index: float, count: int}>,
     *     questions: list<array<string, mixed>>
     * }
     */
    public function summary(ProgramEvaluation $evaluation): array
    {
        $evaluation->loadMissing('questions');
        $answers = EvaluationAnswer::query()
            ->whereIn('evaluation_question_id', $evaluation->questions->pluck('id'))
            ->with('response.user')
            ->get()
            ->groupBy('evaluation_question_id');

        $roles = [];
        foreach ([ProgramEvaluation::ROLE_PARTICIPANT, ProgramEvaluation::ROLE_TEACHER] as $role) {
            if ($query = $evaluation->respondentsQuery($role)) {
                $roles[$role] = [
                    'target' => $query->count(),
                    'responded' => $evaluation->responses()->where('respondent_role', $role)->count(),
                ];
            }
        }

        $target = array_sum(array_column($roles, 'target'));
        $responded = $evaluation->responses()->count();
        $likertScores = $answers->flatten()->filter(fn (EvaluationAnswer $a) => $a->score !== null)->pluck('score');

        return [
            'target' => $target,
            'responded' => $responded,
            'rate' => $target > 0 ? round(min(100, $responded / $target * 100), 1) : null,
            'roles' => $roles,
            'index' => $likertScores->isEmpty() ? null : self::toIndex($likertScores->avg()),
            'aspects' => $this->aspects($evaluation->questions, $answers),
            'questions' => $evaluation->questions->map(fn (EvaluationQuestion $q) => $this->question($q, $answers->get($q->id, collect()), $evaluation->is_anonymous))->all(),
        ];
    }

    /** Skala 1–5 ke indeks 0–100 (1 = 0, 5 = 100). */
    public static function toIndex(float $average): float
    {
        return round(($average - 1) / 4 * 100, 1);
    }

    /**
     * @param  Collection<int, EvaluationQuestion>  $questions
     * @param  Collection<int, Collection<int, EvaluationAnswer>>  $answers
     * @return list<array{aspect: string, average: float, index: float, count: int}>
     */
    protected function aspects(Collection $questions, Collection $answers): array
    {
        return $questions->where('type', EvaluationQuestionType::Likert)
            ->groupBy(fn (EvaluationQuestion $q) => $q->aspect ?: 'Umum')
            ->map(function (Collection $group, string $aspect) use ($answers) {
                $scores = $group->flatMap(fn (EvaluationQuestion $q) => $answers->get($q->id, collect())->pluck('score'))->filter();

                return $scores->isEmpty() ? null : [
                    'aspect' => $aspect,
                    'average' => round($scores->avg(), 2),
                    'index' => self::toIndex($scores->avg()),
                    'count' => $scores->count(),
                ];
            })
            ->filter()
            ->sortBy('index')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, EvaluationAnswer>  $answers
     * @return array<string, mixed>
     */
    protected function question(EvaluationQuestion $question, Collection $answers, bool $anonymous): array
    {
        $row = ['question' => $question, 'count' => $answers->count()];

        return $row + match ($question->type) {
            EvaluationQuestionType::Likert => [
                'average' => $answers->isEmpty() ? null : round($answers->avg('score'), 2),
                'index' => $answers->isEmpty() ? null : self::toIndex($answers->avg('score')),
                'distribution' => collect(range(1, 5))->mapWithKeys(fn ($s) => [$s => $answers->where('score', $s)->count()])->all(),
            ],
            EvaluationQuestionType::Choice => [
                'choices' => collect($question->options ?? [])->mapWithKeys(fn ($o) => [$o => $answers->where('value', $o)->count()])->sortDesc()->all(),
            ],
            EvaluationQuestionType::Text => [
                // Nama penulis hanya tampil pada survei tidak anonim.
                'texts' => $answers->sortByDesc(fn ($a) => $a->response->submitted_at)->map(fn (EvaluationAnswer $a) => [
                    'text' => $a->value,
                    'author' => $anonymous ? null : $a->response->user?->name,
                ])->values()->all(),
            ],
        };
    }
}
