<?php

namespace App\Services;

use App\Models\AssessmentAspect;
use App\Models\AssessmentScore;
use App\Models\Classroom;
use Illuminate\Support\Collection;

/**
 * Aspek penilaian periodik per kelas: pilihan pengajar dari master aspek, atau aspek bawaan bila belum memilih.
 */
class PeriodicAspects
{
    /**
     * Aspek aktif yang boleh dipilih untuk kelas (aspek umum + aspek khusus bahasa kelas).
     *
     * @return Collection<int, AssessmentAspect>
     */
    public function available(Classroom $classroom): Collection
    {
        return AssessmentAspect::query()->active()->forLanguage($classroom->language_id)->with('language')->ordered()->get();
    }

    /** Pengajar sudah memilih aspek sendiri untuk kelas ini? */
    public function isCustomized(Classroom $classroom): bool
    {
        return $classroom->assessmentAspects()->active()->forLanguage($classroom->language_id)->exists();
    }

    /**
     * Aspek yang dinilai di kelas.
     *
     * @return Collection<int, AssessmentAspect>
     */
    public function selected(Classroom $classroom): Collection
    {
        $chosen = $classroom->assessmentAspects()->active()->forLanguage($classroom->language_id)->with('language')->ordered()->get();

        return $chosen->isNotEmpty() ? $chosen : $this->defaults($classroom);
    }

    /** @return Collection<int, AssessmentAspect> */
    public function defaults(Classroom $classroom): Collection
    {
        return $this->available($classroom)->where('is_default', true)->values();
    }

    /**
     * Kolom untuk satu periode: aspek terpilih ditambah aspek yang sudah berisi nilai pada periode itu
     * (agar nilai lama tetap terlihat walau aspeknya tidak dipilih lagi).
     *
     * @param  Collection<int, int>  $assessmentIds
     * @return Collection<int, AssessmentAspect>
     */
    public function forPeriod(Classroom $classroom, Collection $assessmentIds): Collection
    {
        $selected = $this->selected($classroom);
        $used = AssessmentScore::query()->whereIn('assessment_id', $assessmentIds)->distinct()->pluck('assessment_aspect_id');
        $extra = AssessmentAspect::query()->whereIn('id', $used->diff($selected->pluck('id')))->get();

        return self::sort($selected->concat($extra));
    }

    /**
     * @param  Collection<int, AssessmentAspect>  $aspects
     * @return Collection<int, AssessmentAspect>
     */
    public static function sort(Collection $aspects): Collection
    {
        return $aspects->sortBy([['sort_order', 'asc'], ['name', 'asc']])->values();
    }
}
