<?php

namespace App\Actions\Leave;

use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\SessionAttendance;
use Carbon\CarbonInterface;

/**
 * Isi status izin/sakit pada absen harian dan kehadiran sesi selama rentang izin yang disetujui.
 * Kehadiran yang sudah tercatat hadir/terlambat tidak ditimpa.
 */
class SyncLeaveAttendance
{
    public function handle(LeaveRequest $leave): void
    {
        $status = $leave->type->attendanceStatus();
        $note = 'Izin disetujui: '.$leave->reason;

        for ($date = $leave->date_from->copy(); $date->lte($leave->date_to); $date = $date->addDay()) {
            $this->syncDaily($leave, $date, $status, $note);
        }

        $classroomIds = $leave->user->classrooms()->pluck('classrooms.id');

        LearningSession::query()
            ->whereIn('classroom_id', $classroomIds)
            ->whereDateBetween('date', $leave->date_from->toDateString(), $leave->date_to->toDateString())
            ->get()
            ->each(function (LearningSession $session) use ($leave, $status, $note) {
                $existing = SessionAttendance::query()->where('learning_session_id', $session->id)->where('user_id', $leave->user_id)->first();

                if ($existing?->status?->countsAsAttended()) {
                    return;
                }

                SessionAttendance::query()->updateOrCreate(
                    ['learning_session_id' => $session->id, 'user_id' => $leave->user_id],
                    ['status' => $status, 'marked_by' => $leave->reviewed_by, 'note' => $note],
                );
            });
    }

    protected function syncDaily(LeaveRequest $leave, CarbonInterface $date, $status, string $note): void
    {
        $daily = DailyAttendance::query()->where('user_id', $leave->user_id)->whereDate('date', $date)->first();

        if ($daily?->isAttended()) {
            return;
        }

        $daily ??= new DailyAttendance(['user_id' => $leave->user_id, 'date' => $date->toDateString(), 'attempts' => 0]);
        $daily->fill(['status' => $status, 'approved_by' => $leave->reviewed_by, 'approval_note' => $note, 'reject_reason' => null])->save();
    }
}
