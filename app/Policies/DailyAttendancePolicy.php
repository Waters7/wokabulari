<?php

namespace App\Policies;

use App\Models\DailyAttendance;
use App\Models\User;

class DailyAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.approve') || $user->can('attendance.view-all');
    }

    public function view(User $user, DailyAttendance $attendance): bool
    {
        if ($attendance->user_id === $user->id || $user->can('attendance.view-all')) {
            return true;
        }

        return $user->can('attendance.approve')
            && ($user->can('participant.manage') || $attendance->user->classrooms()->taughtBy($user)->exists());
    }

    /** Peserta absen untuk dirinya sendiri. */
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    /** Persetujuan manual: admin atau pengajar kelas peserta. */
    public function approve(User $user, User $participant): bool
    {
        if (! $user->can('attendance.approve')) {
            return false;
        }

        return $user->can('participant.manage') || $participant->classrooms()->taughtBy($user)->exists();
    }

    /** Selfie hanya untuk peserta sendiri dan petugas yang berwenang (bukan wali). */
    public function viewSelfie(User $user, DailyAttendance $attendance): bool
    {
        return $this->view($user, $attendance) && ! $user->isGuardian();
    }
}
