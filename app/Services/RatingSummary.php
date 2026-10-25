<?php

namespace App\Services;

use App\Models\TeacherRating;
use App\Models\VendorRating;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Rekap rating vendor & pengajar: rata-rata keseluruhan dan per aspek.
 */
class RatingSummary
{
    /**
     * @param  array<string, string>  $aspects
     * @return array{count: int, rating: ?float, aspects: array<string, ?float>}
     */
    public function averages(Collection $ratings, array $aspects): array
    {
        $avg = fn (string $column) => ($v = $ratings->whereNotNull($column)->avg($column)) !== null ? round((float) $v, 2) : null;

        return [
            'count' => $ratings->count(),
            'rating' => $avg('rating'),
            'aspects' => collect($aspects)->mapWithKeys(fn ($label, $key) => [$key => $avg($key)])->all(),
        ];
    }

    /**
     * Rata-rata per vendor pada rentang minggu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function vendors(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->weekRange(VendorRating::query(), $from, $to)->with(['vendor', 'user'])->latest()->get()
            ->groupBy('vendor_id')
            ->map(fn (Collection $ratings) => ['vendor' => $ratings->first()->vendor, 'ratings' => $ratings] + $this->averages($ratings, VendorRating::ASPECTS))
            ->sortByDesc('rating')->values();
    }

    /**
     * Rata-rata per pengajar pada rentang minggu (opsional hanya satu pengajar).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function teachers(CarbonInterface $from, CarbonInterface $to, ?int $teacherId = null): Collection
    {
        return $this->weekRange(TeacherRating::query(), $from, $to)
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->with(['teacher', 'user', 'classroom.language'])->latest()->get()
            ->groupBy('teacher_id')
            ->map(fn (Collection $ratings) => ['teacher' => $ratings->first()->teacher, 'ratings' => $ratings] + $this->averages($ratings, TeacherRating::ASPECTS))
            ->sortByDesc('rating')->values();
    }

    /**
     * Tren rata-rata per minggu untuk grafik.
     *
     * @return array{labels: list<string>, values: list<?float>}
     */
    public function weeklyTrend(Builder $query, int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $rows = $query->whereDate('week_start', '>=', $start)->get(['week_start', 'rating'])
            ->groupBy(fn ($r) => $r->week_start->toDateString());

        $labels = [];
        $values = [];
        for ($i = 0; $i < $weeks; $i++) {
            $week = $start->addWeeks($i);
            $labels[] = tanggal($week, 'j M');
            $values[] = ($group = $rows->get($week->toDateString())) ? round((float) $group->avg('rating'), 2) : null;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function weekRange(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereDate('week_start', '>=', $from->copy()->startOfWeek())->whereDate('week_start', '<=', $to->copy()->startOfWeek());
    }
}
