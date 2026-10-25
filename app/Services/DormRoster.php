<?php

namespace App\Services;

use App\Enums\DormAttendanceStatus;
use App\Enums\Role;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\Dormitory;
use App\Models\DormRoom;
use App\Models\HealthReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Daftar penghuni asrama per malam beserta status efektif (absen, izin keluar, sakit).
 */
class DormRoster
{
    /** @return Collection<int, User> */
    public function residents(Dormitory $dormitory): Collection
    {
        return User::query()->role(Role::Participant->value)->active()
            ->whereHas('roomAssignments', fn ($q) => $q->whereNull('check_out_date')->whereHas('room', fn ($r) => $r->where('dormitory_id', $dormitory->id)))
            ->with(['profile', 'currentRoomAssignment.room'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, array{user: User, room: ?DormRoom, attendance: ?DormAttendance, permit: ?DormExitPermit, sick: ?HealthReport, status: ?DormAttendanceStatus}>
     */
    public function night(Dormitory $dormitory, CarbonInterface $date): Collection
    {
        $residents = $this->residents($dormitory);
        $ids = $residents->pluck('id');

        $attendances = DormAttendance::query()->whereIn('user_id', $ids)->whereDate('date', $date)->with('confirmer')->get()->keyBy('user_id');
        $permits = DormExitPermit::query()->whereIn('user_id', $ids)->covering(CarbonImmutable::parse($date))->get()->keyBy('user_id');
        $sick = HealthReport::query()->whereIn('user_id', $ids)->open()->whereDate('reported_at', '<=', $date)->latest('reported_at')->get()->unique('user_id')->keyBy('user_id');

        return $residents->map(function (User $user) use ($attendances, $permits, $sick) {
            $attendance = $attendances->get($user->id);
            $permit = $permits->get($user->id);
            $report = $sick->get($user->id);

            $status = $attendance?->status;
            if ($status === null || $status === DormAttendanceStatus::Rejected) {
                $status = $permit ? DormAttendanceStatus::OnLeave : ($report ? DormAttendanceStatus::SickInRoom : $status);
            }

            return [
                'user' => $user,
                'room' => $user->currentRoomAssignment?->room,
                'attendance' => $attendance,
                'permit' => $permit,
                'sick' => $report,
                'status' => $status,
            ];
        })->values();
    }

    /** Penghuni yang belum tercatat di asrama (tanpa izin keluar/sakit). */
    public function missing(Dormitory $dormitory, CarbonInterface $date): Collection
    {
        return $this->night($dormitory, $date)
            ->filter(fn (array $row) => $row['status'] === null || $row['status'] === DormAttendanceStatus::Rejected)
            ->values();
    }

    /**
     * Tutup absen satu malam: buat catatan untuk yang belum tercatat (tidak di asrama / izin / sakit).
     */
    public function closeNight(CarbonInterface $date): int
    {
        $created = 0;

        foreach (Dormitory::query()->get() as $dormitory) {
            foreach ($this->night($dormitory, $date) as $row) {
                if ($row['attendance'] && $row['attendance']->status !== DormAttendanceStatus::Rejected) {
                    continue;
                }

                $attendance = $row['attendance'] ?? new DormAttendance(['user_id' => $row['user']->id, 'date' => $date->toDateString(), 'attempts' => 0]);
                $attendance->fill([
                    'dormitory_id' => $dormitory->id,
                    'status' => $row['status'] === DormAttendanceStatus::Rejected || $row['status'] === null ? DormAttendanceStatus::NotPresent : $row['status'],
                    'note' => $attendance->note ?? 'Ditutup otomatis',
                ])->save();
                $created++;
            }
        }

        return $created;
    }

    /**
     * Penanda konsumsi: peserta izin keluar atau sakit pada tanggal tertentu.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, list<string>>
     */
    public static function mealFlags(CarbonInterface $date, Collection $userIds): array
    {
        $flags = [];

        DormExitPermit::query()->whereIn('user_id', $userIds)->covering(CarbonImmutable::parse($date))->get()
            ->each(function (DormExitPermit $permit) use (&$flags) {
                $flags[$permit->user_id][] = 'Izin keluar ('.$permit->type->label().')';
            });

        HealthReport::query()->whereIn('user_id', $userIds)->open()->get()->unique('user_id')
            ->each(function (HealthReport $report) use (&$flags) {
                $flags[$report->user_id][] = 'Sakit ('.$report->severity->label().')';
            });

        return $flags;
    }
}
