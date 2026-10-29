<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\Batch;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\User;
use App\Support\AttendanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kehadiran harian & per sesi untuk dashboard eksekutif dan laporan.
 * Hari yang dihitung: hari absen sesuai jadwal angkatan, dalam masa angkatan, sampai hari ini.
 */
class AttendanceAnalytics
{
    /** Status yang dihitung, plus "missing" (tidak/belum absen). */
    public const KEYS = ['present', 'late', 'excused', 'sick', 'absent', 'rejected', 'missing'];

    /** @var array<int, AttendanceSchedule> */
    protected array $schedules = [];

    /**
     * @return array{
     *     participants: Collection<int, array{user: User, expected: int, counts: array<string, int>, attended: int, rate: ?float}>,
     *     days: Collection<string, array{date: CarbonImmutable, expected: int, counts: array<string, int>, attended: int, rate: ?float}>,
     *     totals: array<string, int>, expected: int, attended: int, rate: ?float
     * }
     */
    public function daily(ReportFilter $filter): array
    {
        $participants = $filter->participants();
        $records = DailyAttendance::query()->whereIn('user_id', $filter->participantIds())
            ->whereDateBetween('date', $filter->from->toDateString(), $filter->to->toDateString())
            ->get(['id', 'user_id', 'date', 'status', 'checked_in_at'])
            ->groupBy('user_id')->map(fn (Collection $rows) => $rows->keyBy(fn (DailyAttendance $r) => $r->date->toDateString()));

        $days = [];
        foreach ($filter->pastDays() as $day) {
            $days[$day->toDateString()] = ['date' => $day, 'expected' => 0, 'counts' => $this->emptyCounts()];
        }

        $rows = [];
        foreach ($participants as $user) {
            $batch = $user->classrooms->first()?->batch;
            $counts = $this->emptyCounts();
            $expected = 0;

            foreach ($days as $key => $day) {
                if (! $this->isExpected($batch, $day['date'])) {
                    continue;
                }

                $status = $records->get($user->id)?->get($key)?->status?->value ?? 'missing';
                $counts[$status]++;
                $expected++;
                $days[$key]['expected']++;
                $days[$key]['counts'][$status]++;
            }

            $rows[] = ['user' => $user, 'expected' => $expected, 'counts' => $counts] + $this->rate($counts, $expected);
        }

        $rows = collect($rows);
        $days = collect($days)->map(fn (array $day) => $day + $this->rate($day['counts'], $day['expected']));
        $totals = collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => $rows->sum(fn ($row) => $row['counts'][$key])])->all();
        $expected = (int) $rows->sum('expected');

        return ['participants' => $rows, 'days' => $days, 'totals' => $totals, 'expected' => $expected] + $this->rate($totals, $expected);
    }

    /**
     * Absen harian satu tanggal: status tiap peserta (untuk "siapa saja hadir").
     *
     * @return Collection<int, array{user: User, scheduled: bool, record: ?DailyAttendance}>
     */
    public function roster(ReportFilter $filter, CarbonImmutable $date): Collection
    {
        $records = DailyAttendance::query()->whereIn('user_id', $filter->participantIds())->whereDate('date', $date)->get()->keyBy('user_id');

        return $filter->participants()->map(fn (User $user) => [
            'user' => $user,
            'scheduled' => $this->isExpected($user->classrooms->first()?->batch, $date),
            'record' => $records->get($user->id),
        ])->values();
    }

    /**
     * Kehadiran per sesi (sesi tidak dibatalkan, sampai hari ini).
     *
     * @return Collection<int, array{session: LearningSession, expected: int, counts: array<string, int>, attended: int, rate: ?float, attendances: Collection}>
     */
    public function sessions(ReportFilter $filter): Collection
    {
        $participants = $filter->participants();
        $byClass = $participants->flatMap(fn (User $user) => $user->classrooms->map(fn ($c) => [$c->id, $user->id]))
            ->groupBy(fn ($pair) => $pair[0])->map(fn ($pairs) => $pairs->pluck(1)->all());

        return LearningSession::query()->with(['classroom', 'teacher', 'attendances'])
            ->whereIn('classroom_id', $filter->classroomIds())
            ->whereDateBetween('date', $filter->from->toDateString(), $filter->to->min(today()->toImmutable())->toDateString())
            ->where('status', '!=', SessionStatus::Cancelled)
            ->orderBy('date')->orderBy('start_time')->get()
            ->map(function (LearningSession $session) use ($byClass) {
                $members = $byClass->get($session->classroom_id, []);
                $attendances = $session->attendances->whereIn('user_id', $members)->keyBy('user_id');
                $counts = $this->emptyCounts();

                foreach ($members as $userId) {
                    $counts[$attendances->get($userId)?->status?->value ?? 'missing']++;
                }

                return ['session' => $session, 'expected' => count($members), 'counts' => $counts, 'attendances' => $attendances] + $this->rate($counts, count($members));
            });
    }

    /** @return array<string, int> */
    public function emptyCounts(): array
    {
        return array_fill_keys(self::KEYS, 0);
    }

    /** Label status untuk tabel, termasuk "Tidak absen". */
    public static function label(string $key): string
    {
        return $key === 'missing' ? 'Tidak absen' : AttendanceStatus::from($key)->label();
    }

    protected function isExpected(?Batch $batch, CarbonImmutable $date): bool
    {
        if ($batch && ($date->lt($batch->start_date->startOfDay()) || $date->gt($batch->end_date->startOfDay()))) {
            return false;
        }

        $schedule = $this->schedules[$batch?->id ?? 0] ??= AttendanceSchedule::forBatch($batch);

        return $schedule->isScheduledDay($date);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{attended: int, rate: ?float}
     */
    protected function rate(array $counts, int $expected): array
    {
        $attended = $counts['present'] + $counts['late'];

        return ['attended' => $attended, 'rate' => $expected > 0 ? round($attended / $expected * 100, 1) : null];
    }
}
