<?php

namespace App\Services;

use App\Enums\LanguageAspect;
use App\Models\AssessmentAspect;
use App\Models\Classroom;
use App\Models\SessionScore;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Saran nilai awal penilaian periodik dari rata-rata komponen sesi (termasuk komponen quiz & tugas
 * yang ditautkan ke sesi) dalam periode tersebut. Tiap master aspek mengambil saran dari
 * aspek bahasa sumbernya (source_aspect).
 */
class AssessmentSuggester
{
    /** Penyedia tambahan (mis. quiz/tugas yang tidak terikat sesi). */
    protected static array $extraSources = [];

    /**
     * @param  callable(Classroom, int, CarbonInterface, CarbonInterface): array<string, list<float>>  $source
     */
    public static function extend(callable $source): void
    {
        static::$extraSources[] = $source;
    }

    /**
     * @param  Collection<int, AssessmentAspect>  $aspects
     * @return array<int, float|null> id aspek => saran nilai 0–100
     */
    public function suggest(Classroom $classroom, int $userId, CarbonInterface $from, CarbonInterface $to, Collection $aspects): array
    {
        $buckets = $this->buckets($classroom, $userId, $from, $to);
        // Partisipasi masuk ke Sikap, kecuali kelas memakai aspek partisipasi tersendiri.
        $separateParticipation = $aspects->contains(fn (AssessmentAspect $a) => $a->source_aspect === LanguageAspect::Participation);

        return $aspects->mapWithKeys(function (AssessmentAspect $aspect) use ($buckets, $separateParticipation) {
            $values = $aspect->source_aspect ? $buckets[$aspect->source_aspect->value] : [];
            if ($aspect->source_aspect === LanguageAspect::Attitude && ! $separateParticipation) {
                $values = array_merge($values, $buckets[LanguageAspect::Participation->value]);
            }

            return [$aspect->id => GradeService::average($values)];
        })->all();
    }

    /**
     * Nilai (0–100) per aspek bahasa dalam periode.
     *
     * @return array<string, list<float>>
     */
    protected function buckets(Classroom $classroom, int $userId, CarbonInterface $from, CarbonInterface $to): array
    {
        $buckets = collect(LanguageAspect::cases())->mapWithKeys(fn ($a) => [$a->value => []])->all();

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
            $buckets[$component->aspect->value][] = SessionScoreCalculator::normalize($score->score, $component->max_score);
        }

        foreach (static::$extraSources as $source) {
            foreach ($source($classroom, $userId, $from, $to) as $aspect => $values) {
                $buckets[$aspect] = array_merge($buckets[$aspect] ?? [], $values);
            }
        }

        return $buckets;
    }
}
