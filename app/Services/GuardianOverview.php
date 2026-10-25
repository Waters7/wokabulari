<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DormAttendanceStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\DailyAttendance;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\HealthReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Ringkasan data anak untuk portal wali — tanpa selfie, data peserta lain, atau rating anonim.
 */
class GuardianOverview
{
    /** Penanda tambahan dari fase berikutnya (poin, level, lencana). */
    protected static array $extras = [];

    /**
     * @param  callable(User): (array<string, mixed>|null)  $extra
     */
    public static function extend(string $key, callable $extra): void
    {
        static::$extras[$key] = $extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $child): array
    {
        $today = CarbonImmutable::today();

        return [
            'classToday' => DailyAttendance::query()->where('user_id', $child->id)->whereDate('date', $today)->first(),
            'dormToday' => DormAttendance::query()->where('user_id', $child->id)->whereDate('date', $today)->first(),
            'attendanceRate' => $this->attendanceRate($child, $today->subDays(29), $today),
            'assignmentRate' => $this->assignmentRate($child),
            'exits' => DormExitPermit::query()->where('user_id', $child->id)->latest('planned_departure')->limit(5)->get(),
            'health' => HealthReport::query()->where('user_id', $child->id)->latest('reported_at')->limit(5)->get(),
            'room' => $child->currentRoomAssignment?->room,
            'extras' => collect(static::$extras)->map(fn ($extra) => $extra($child))->filter()->all(),
        ];
    }

    /** Persentase hadir/terlambat dari absen harian yang tercatat. */
    public function attendanceRate(User $child, CarbonInterface $from, CarbonInterface $to): ?int
    {
        $records = DailyAttendance::query()->where('user_id', $child->id)->whereDateBetween('date', $from->toDateString(), $to->toDateString())
            ->where('status', '!=', AttendanceStatus::Rejected)->get();

        return $records->isEmpty() ? null : (int) round($records->filter->isAttended()->count() / $records->count() * 100);
    }

    /** Persentase tugas terbit (yang tenggatnya lewat) yang sudah dikumpulkan. */
    public function assignmentRate(User $child): ?int
    {
        $classroomIds = $child->classrooms()->pluck('classrooms.id');
        $due = Assignment::query()->whereIn('classroom_id', $classroomIds)->where('status', AssignmentStatus::Published)->where('due_at', '<=', now())->pluck('id');

        if ($due->isEmpty()) {
            return null;
        }

        $submitted = AssignmentSubmission::query()->where('user_id', $child->id)->whereIn('assignment_id', $due)->distinct('assignment_id')->count('assignment_id');

        return (int) round($submitted / $due->count() * 100);
    }

    /**
     * Kalender satu bulan: status kelas & asrama per tanggal.
     *
     * @return Collection<string, array{date: CarbonImmutable, class: ?AttendanceStatus, dorm: ?DormAttendanceStatus}>
     */
    public function calendar(User $child, CarbonInterface $month): Collection
    {
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();

        $class = DailyAttendance::query()->where('user_id', $child->id)->whereDateBetween('date', $start->toDateString(), $end->toDateString())
            ->get()->keyBy(fn ($a) => $a->date->toDateString());
        $dorm = DormAttendance::query()->where('user_id', $child->id)->whereDateBetween('date', $start->toDateString(), $end->toDateString())
            ->get()->keyBy(fn ($a) => $a->date->toDateString());

        return collect(range(0, $start->daysInMonth - 1))->mapWithKeys(function ($i) use ($start, $class, $dorm) {
            $date = $start->addDays($i);
            $key = $date->toDateString();

            return [$key => ['date' => $date, 'class' => $class->get($key)?->status, 'dorm' => $dorm->get($key)?->status]];
        });
    }
}
