<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Events\DailyAttendanceRecorded;
use App\Models\DailyAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Persetujuan/penandaan manual absen harian oleh admin atau pengajar (tercatat di log).
 */
class ApproveDailyAttendance
{
    public function handle(User $approver, User $participant, Carbon|string $date, AttendanceStatus $status, string $note): DailyAttendance
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('Alasan wajib diisi.');
        }

        if ($status === AttendanceStatus::Rejected) {
            throw new InvalidArgumentException('Status tidak valid.');
        }

        $date = Carbon::parse($date)->toDateString();

        $attendance = DailyAttendance::query()->where('user_id', $participant->id)->whereDate('date', $date)->first()
            ?? new DailyAttendance(['user_id' => $participant->id, 'date' => $date]);
        $wasAttended = $attendance->exists && $attendance->status->countsAsAttended();

        $attendance->fill([
            'status' => $status,
            'checked_in_at' => $attendance->checked_in_at ?? Carbon::parse($date)->setTimeFromTimeString((string) setting('attendance.late_after')),
            'approved_by' => $approver->id,
            'approval_note' => trim($note),
        ]);
        $attendance->attempts ??= 0;
        $attendance->save();

        activity('kehadiran')->causedBy($approver)->performedOn($attendance)
            ->withProperties(['peserta' => $participant->name, 'tanggal' => $date, 'status' => $status->value, 'alasan' => $note])
            ->log('Menandai absen harian secara manual');

        if (! $wasAttended && $status->countsAsAttended()) {
            event(new DailyAttendanceRecorded($attendance));
        }

        return $attendance;
    }
}
