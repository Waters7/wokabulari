<?php

namespace App\Services;

use App\Enums\LanguageAspect;
use App\Models\Classroom;
use App\Models\SessionScore;
use Carbon\CarbonInterface;

/**
 * Saran nilai awal penilaian periodik dari rata-rata komponen sesi (termasuk komponen quiz & tugas
 * yang ditautkan ke sesi) dalam periode tersebut, per aspek bahasa.
 */
class AssessmentSuggester
{
    /** Penyedia tambahan (mis. quiz/tugas yang tidak terikat sesi) — didaftarkan fase berikutnya. */
    protected static array $extraSources = [];

    /**
     * @param  callable(Classroom, int, CarbonInterface, CarbonInterface): array<string, list<float>>  $source
     */
    public static function extend(callable $source): void
    {
        static::$extraSources[] = $source;
    }

    /**
     * @return array<string, float|null> aspek periodik => saran nilai 0–100
     */
    public function suggest(Classroom $classroom, int $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        $buckets = collect(LanguageAspect::periodic())->mapWithKeys(fn ($a) => [$a->value => []])->all();

        $scores = SessionScore::query()
            ->where('user_id', $userId)
            ->whereNotNull('score')
            ->whereHas('component.session', fn ($q) => $q
                ->where('classroom_id', $classroom->id)
                ->whereDateBetween('date', $from->toDateString(), $to->toDateString()))
            ->with('component')
            ->get();

        foreach ($scores as $score) {
            $component = $score->component;
            $buckets[$component->aspect->assessmentColumn()][] = SessionScoreCalculator::normalize($score->score, $component->max_score);
        }

        foreach (static::$extraSources as $source) {
            foreach ($source($classroom, $userId, $from, $to) as $aspect => $values) {
                $column = LanguageAspect::from($aspect)->assessmentColumn();
                $buckets[$column] = array_merge($buckets[$column], $values);
            }
        }

        return collect($buckets)->map(fn ($values) => GradeService::average($values))->all();
    }
}
