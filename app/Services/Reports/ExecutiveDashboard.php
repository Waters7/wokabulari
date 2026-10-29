<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Models\Classroom;
use App\Models\MealReceipt;
use App\Models\PointTransaction;
use App\Services\GradeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Data dashboard eksekutif (pimpinan & admin program): angka utama, grafik, catatan keputusan,
 * dan daftar peserta. Hasil disimpan 5 menit per filter (SPEC 3.24).
 */
class ExecutiveDashboard
{
    public const CACHE_SECONDS = 300;

    public function __construct(
        protected AttendanceAnalytics $attendance,
        protected ScoreAnalytics $scores,
        protected MealAnalytics $meals,
    ) {}

    /**
     * @return array<string, mixed> hanya nilai skalar/array (aman disimpan di cache)
     */
    public function overview(ReportFilter $filter): array
    {
        return Cache::remember($filter->cacheKey('eksekutif'), self::CACHE_SECONDS, fn () => $this->compute($filter));
    }

    /**
     * @return array<string, mixed>
     */
    protected function compute(ReportFilter $filter): array
    {
        $batch = $filter->batchId ? Batch::query()->find($filter->batchId) : null;
        $target = (float) ($batch?->attendance_target ?? 80);
        $passing = (float) ($batch?->passing_score ?? 70);

        $attendance = $this->attendance->daily($filter);
        $sessions = $this->attendance->sessions($filter);
        $scores = $this->scores->perParticipant($filter)->keyBy(fn (array $row) => $row['user']->id);
        $weekly = $this->scores->weekly($filter);
        $meals = $this->meals->compute($filter);

        $people = $attendance['participants']->map(function (array $row) use ($scores, $target, $passing) {
            $score = $scores->get($row['user']->id);
            $flags = array_values(array_filter([
                $row['rate'] !== null && $row['rate'] < $target ? 'Kehadiran di bawah target' : null,
                $score && $score['overall'] !== null && $score['overall'] < $passing ? 'Nilai di bawah batas lulus' : null,
                $row['counts']['late'] >= 3 ? 'Sering terlambat' : null,
                $row['counts']['absent'] + $row['counts']['missing'] >= 3 ? 'Sering tidak absen' : null,
            ]));

            return [
                'id' => $row['user']->id,
                'name' => $row['user']->name,
                'classes' => $row['user']->classrooms->pluck('id')->all(),
                'class' => ReportFilter::classLabel($row['user']),
                'attendance' => $row['rate'],
                'late' => $row['counts']['late'],
                'score' => $score['overall'] ?? null,
                'grade' => $score['grade'] ?? null,
                'flags' => $flags,
            ];
        })->values();

        $classes = Classroom::query()->whereIn('id', $filter->classroomIds())->orderBy('name')->get(['id', 'name'])
            ->map(function (Classroom $classroom) use ($people, $sessions) {
                $members = $people->filter(fn ($p) => in_array($classroom->id, $p['classes'], true));
                $classSessions = $sessions->filter(fn ($s) => $s['session']->classroom_id === $classroom->id);
                $expected = (int) $classSessions->sum('expected');

                return [
                    'name' => $classroom->name,
                    'participants' => $members->count(),
                    'attendance' => GradeService::average($members->pluck('attendance')),
                    'sessions' => $expected > 0 ? round($classSessions->sum('attended') / $expected * 100, 1) : null,
                    'score' => GradeService::average($members->pluck('score')),
                ];
            })->filter(fn ($c) => $c['participants'] > 0)->values();

        $sources = collect(ScoreAnalytics::SOURCES)->map(fn (string $label, string $key) => ['label' => $label, 'value' => GradeService::average($scores->pluck($key))]);
        $grades = collect(['A', 'B', 'C', 'D'])->mapWithKeys(fn ($g) => [$g => $people->where('grade', $g)->count()]);
        $sessionExpected = (int) $sessions->sum('expected');
        $sessionRate = $sessionExpected > 0 ? round($sessions->sum('attended') / $sessionExpected * 100, 1) : null;
        $scoreAvg = GradeService::average($people->pluck('score'));
        $leftover = (int) $meals['schedules']->filter(fn ($r) => $r['delivered'] !== null)->sum(fn ($r) => max(0, $r['delivered'] - $r['schedule']->receipts_total));

        $days = $attendance['days']->filter(fn ($d) => $d['expected'] > 0);
        $mealDays = $meals['days'];

        return [
            'target' => $target,
            'passing' => $passing,
            'kpis' => [
                'participants' => $people->count(),
                'attendance' => $attendance['rate'],
                'sessions' => $sessionRate,
                'sessionCount' => $sessions->count(),
                'score' => $scoreAvg,
                'scored' => $people->whereNotNull('score')->count(),
                'meals' => $meals['totals']['rate'],
                'received' => $meals['totals']['received'],
                'leftover' => $leftover,
            ],
            'status' => $attendance['totals'],
            'statusExpected' => $attendance['expected'],
            'charts' => [
                'attendance' => [
                    'labels' => $days->map(fn ($d) => tanggal($d['date'], 'j M'))->values()->all(),
                    'values' => $days->pluck('rate')->values()->all(),
                ],
                'classes' => $classes->all(),
                'weekly' => [
                    'labels' => $weekly->keys()->map(fn ($w) => tanggal($w, 'j M'))->values()->all(),
                    'values' => $weekly->values()->all(),
                ],
                'sources' => $sources->values()->all(),
                'grades' => $grades->all(),
                'meals' => [
                    'labels' => $mealDays->keys()->map(fn ($d) => tanggal($d, 'j M'))->values()->all(),
                    'ordered' => $mealDays->pluck('ordered')->values()->all(),
                    'delivered' => $mealDays->pluck('delivered')->values()->all(),
                    'received' => $mealDays->pluck('received')->values()->all(),
                ],
                'mealTypes' => $meals['types']->map(fn ($t) => ['label' => $t['type']->label(), 'rate' => $t['rate'], 'received' => $t['received'], 'eligible' => $t['eligible']])->values()->all(),
            ],
            'vendors' => $meals['vendors']->map(fn ($v) => [
                'name' => $v['vendor']?->name ?? '–',
                'schedules' => $v['schedules'],
                'delivered' => array_sum($v['delivered']),
                'received' => $v['received'],
            ])->all(),
            'people' => $people->map(fn ($p) => collect($p)->except('classes')->all())->all(),
            'insights' => $this->insights($attendance['rate'], $target, $classes, $people, $scoreAvg, $passing, $sources, $meals, $leftover, $sessionRate, $sessions->count()),
        ];
    }

    /**
     * Ringkasan per peserta per minggu (kehadiran, nilai, rata-rata kelas) untuk drill-down.
     *
     * @return array<string, mixed>|null
     */
    public function participant(ReportFilter $filter, int $userId): ?array
    {
        $user = $filter->participants()->firstWhere('id', $userId);
        if (! $user) {
            return null;
        }

        $own = $filter->forUser($userId);
        $classFilter = new ReportFilter($filter->from, $filter->to, $filter->batchId, $user->classrooms->first()?->id);

        $weeks = collect($filter->weeks())->map(function (CarbonImmutable $week) use ($own, $classFilter, $filter) {
            [$from, $to] = [$week->max($filter->from), $week->endOfWeek()->startOfDay()->min($filter->to)];

            return [
                'label' => tanggal($from, 'j M'),
                'attendance' => $this->attendance->daily($own->between($from, $to))['rate'],
                'score' => $this->scores->perParticipant($own->between($from, $to))->first()['overall'] ?? null,
                'class' => GradeService::average($this->scores->perParticipant($classFilter->between($from, $to))->pluck('overall')),
            ];
        });

        $attendance = $this->attendance->daily($own);
        $scores = $this->scores->perParticipant($own)->first();

        return [
            'name' => $user->name,
            'class' => ReportFilter::classLabel($user),
            'attendance' => $attendance['rate'],
            'counts' => $attendance['totals'],
            'scores' => collect(ScoreAnalytics::SOURCES)->map(fn ($label, $key) => ['label' => $label, 'value' => $scores[$key] ?? null])->values()->all(),
            'overall' => $scores['overall'] ?? null,
            'grade' => $scores['grade'] ?? null,
            'meals' => MealReceipt::query()->where('user_id', $userId)
                ->whereHas('schedule', fn ($q) => $q->whereDateBetween('date', $filter->from->toDateString(), $filter->to->toDateString()))->count(),
            'points' => (int) PointTransaction::query()->where('user_id', $userId)
                ->whereBetween('created_at', [$filter->from->startOfDay(), $filter->to->endOfDay()])->sum('points'),
            'weeks' => $weeks->all(),
        ];
    }

    /**
     * Catatan singkat yang membantu keputusan, hanya bila datanya ada.
     *
     * @return list<array{tone: string, text: string}>
     */
    protected function insights(?float $rate, float $target, Collection $classes, Collection $people, ?float $scoreAvg, float $passing, Collection $sources, array $meals, int $leftover, ?float $sessionRate, int $sessionCount): array
    {
        $notes = [];
        $pct = fn (?float $v) => angka((float) $v, 1).'%';

        if ($rate !== null) {
            $notes[] = ['tone' => $rate >= $target ? 'good' : 'warning', 'text' => 'Kehadiran harian rata-rata '.$pct($rate).' (target '.angka($target).'%).'];
        }

        $lowestClass = $classes->whereNotNull('attendance')->sortBy('attendance')->first();
        if ($lowestClass && $classes->count() > 1) {
            $notes[] = ['tone' => $lowestClass['attendance'] >= $target ? 'info' : 'warning', 'text' => 'Kehadiran terendah di '.$lowestClass['name'].' ('.$pct($lowestClass['attendance']).').'];
        }

        $belowTarget = $people->filter(fn ($p) => in_array('Kehadiran di bawah target', $p['flags'], true))->count();
        if ($belowTarget > 0) {
            $notes[] = ['tone' => 'warning', 'text' => $belowTarget.' peserta kehadirannya di bawah target; lihat daftar Perlu perhatian untuk tindak lanjut wali/pendamping.'];
        }

        if ($sessionRate !== null) {
            $notes[] = ['tone' => $sessionRate >= $target ? 'info' : 'warning', 'text' => $sessionCount.' sesi kelas dengan kehadiran rata-rata '.$pct($sessionRate).'.'];
        }

        if ($scoreAvg !== null) {
            $below = $people->filter(fn ($p) => $p['score'] !== null && $p['score'] < $passing)->count();
            $notes[] = ['tone' => $below === 0 ? 'good' : 'warning', 'text' => 'Rata-rata nilai '.angka($scoreAvg, 1).'; '.$below.' peserta di bawah batas lulus '.angka($passing).'.'];

            $weakest = $sources->whereNotNull('value')->sortBy('value')->first();
            if ($weakest && $sources->whereNotNull('value')->count() > 1 && $weakest['value'] < $passing) {
                $notes[] = ['tone' => 'warning', 'text' => 'Komponen nilai terendah: '.$weakest['label'].' ('.angka($weakest['value'], 1).'). Pertimbangkan latihan atau penguatan materi.'];
            }
        }

        if ($meals['totals']['rate'] !== null) {
            $notes[] = ['tone' => $meals['totals']['rate'] >= 85 ? 'good' : 'info', 'text' => 'Serapan konsumsi '.$pct($meals['totals']['rate']).' ('.angka($meals['totals']['received']).' tanda terima).'];
            $lowestMeal = $meals['types']->whereNotNull('rate')->sortBy('rate')->first();
            if ($lowestMeal && $lowestMeal['rate'] < 80) {
                $notes[] = ['tone' => 'warning', 'text' => 'Serapan terendah: '.$lowestMeal['type']->label().' ('.$pct($lowestMeal['rate']).'). Pertimbangkan menyesuaikan jumlah pesanan atau jam pengambilan.'];
            }
        }

        if ($leftover > 0) {
            $notes[] = ['tone' => 'info', 'text' => angka($leftover).' porsi diterima dari vendor tetapi tidak ditandatangani peserta pada periode ini.'];
        }

        return $notes;
    }
}
