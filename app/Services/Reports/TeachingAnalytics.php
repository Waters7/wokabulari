<?php

namespace App\Services\Reports;

use App\Enums\SessionStatus;
use App\Enums\TeachingStatus;
use App\Models\LearningSession;
use App\Models\SessionAttendanceCode;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Laporan mengajar: pengajar dihitung **hadir** pada sesi bila ia membuat kode kehadiran sesi itu
 * dan ada minimal satu peserta yang hadir (hadir/terlambat). Pengajar yang menjadwalkan sesi tetapi
 * tidak membuat kode tercatat "Tidak tercatat". Jam mengajar = durasi jadwal sesi yang dihadiri.
 */
class TeachingAnalytics
{
    /**
     * Satu baris per (sesi, pengajar).
     *
     * @return Collection<int, array{session: LearningSession, teacher: User, status: TeachingStatus, code_at: ?CarbonInterface, attended: int, expected: int, hours: float}>
     */
    public function rows(ReportFilter $filter, ?int $teacherId = null): Collection
    {
        $sessions = LearningSession::query()
            ->with(['classroom' => fn ($q) => $q->withCount('participants'), 'teacher', 'codes', 'attendances'])
            ->whereIn('classroom_id', $filter->classroomIds())
            ->whereDateBetween('date', $filter->from->toDateString(), $filter->to->min(today()->toImmutable())->toDateString())
            ->where('status', '!=', SessionStatus::Cancelled)
            ->when($teacherId, fn ($q) => $q->where(fn ($w) => $w->where('teacher_id', $teacherId)
                ->orWhereHas('codes', fn ($c) => $c->where('generated_by', $teacherId))))
            ->orderBy('date')->orderBy('start_time')->get();

        $generatorIds = $sessions->flatMap(fn (LearningSession $s) => $s->codes->pluck('generated_by'))->filter()->unique();
        $users = User::query()->whereIn('id', $generatorIds->merge($sessions->pluck('teacher_id'))->filter()->unique())->get()->keyBy('id');

        return $sessions->flatMap(function (LearningSession $session) use ($users, $teacherId) {
            $attended = $session->attendances->filter(fn ($a) => $a->status->countsAsAttended())->count();
            $firstCode = $session->codes->whereNotNull('generated_by')->groupBy('generated_by')
                ->map(fn (Collection $codes) => $codes->min('created_at'));
            // Pengajar terjadwal + siapa pun pengajar kelas yang membuat kode.
            $teacherIds = collect([$session->teacher_id])->merge($firstCode->keys())->filter()->unique()
                ->filter(fn ($id) => (int) $id === (int) $session->teacher_id || $session->classroom->hasTeacher($users->get($id)))
                ->when($teacherId, fn ($ids) => $ids->filter(fn ($id) => (int) $id === $teacherId));
            $hours = round($session->startsAt()->diffInMinutes($session->endsAt()) / 60, 2);

            return $teacherIds->map(function ($id) use ($session, $users, $firstCode, $attended, $hours) {
                $codeAt = $firstCode->get($id);
                $status = match (true) {
                    $codeAt !== null && $attended > 0 => TeachingStatus::Present,
                    $codeAt !== null => TeachingStatus::NoParticipants,
                    $session->status === SessionStatus::Scheduled && $session->startsAt()->isFuture() => TeachingStatus::Upcoming,
                    default => TeachingStatus::NotRecorded,
                };

                return [
                    'session' => $session,
                    'teacher' => $users->get($id),
                    'status' => $status,
                    'code_at' => $codeAt,
                    'attended' => $attended,
                    'expected' => (int) $session->classroom->participants_count,
                    'hours' => $status === TeachingStatus::Present ? $hours : 0.0,
                ];
            })->filter(fn ($row) => $row['teacher'] !== null);
        })->values();
    }

    /**
     * Rekap per pengajar.
     *
     * @return Collection<int, array{teacher: User, classes: string, sessions: int, present: int, no_participants: int, not_recorded: int, hours: float, avg_attended: ?float}>
     */
    public function perTeacher(Collection $rows): Collection
    {
        return $rows->groupBy(fn ($row) => $row['teacher']->id)->map(fn (Collection $items) => [
            'teacher' => $items->first()['teacher'],
            'classes' => $items->map(fn ($r) => $r['session']->classroom->name)->unique()->join(', '),
            'sessions' => $items->where('status', '!=', TeachingStatus::Upcoming)->count(),
            'present' => $items->where('status', TeachingStatus::Present)->count(),
            'no_participants' => $items->where('status', TeachingStatus::NoParticipants)->count(),
            'not_recorded' => $items->where('status', TeachingStatus::NotRecorded)->count(),
            'hours' => round((float) $items->sum('hours'), 2),
            'avg_attended' => ($present = $items->where('status', TeachingStatus::Present))->isEmpty() ? null : round((float) $present->avg('attended'), 1),
        ])->sortBy(fn ($row) => $row['teacher']->name, SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** Pengajar yang sudah tercatat hadir pada sesi ini (untuk halaman sesi). */
    public static function isPresent(LearningSession $session, User $teacher): bool
    {
        return SessionAttendanceCode::query()->where('learning_session_id', $session->id)->where('generated_by', $teacher->id)->exists()
            && $session->attendances()->whereIn('status', ['present', 'late'])->exists();
    }
}
