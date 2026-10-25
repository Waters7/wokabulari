<?php

namespace App\Policies;

use App\Models\DormAttendance;
use App\Models\User;

class DormAttendancePolicy
{
    /** Peserta absen asrama sendiri. */
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    /** Pengelola asrama melihat daftar, mengonfirmasi, dan apel manual. */
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.confirm-dorm');
    }

    public function view(User $user, DormAttendance $attendance): bool
    {
        return $attendance->user_id === $user->id || $user->can('attendance.confirm-dorm') || $user->isGuardianOf($attendance->user);
    }

    public function confirm(User $user, ?DormAttendance $attendance = null): bool
    {
        return $user->can('attendance.confirm-dorm');
    }

    /** Selfie hanya untuk petugas asrama (wali & pimpinan tidak). */
    public function viewSelfie(User $user, DormAttendance $attendance): bool
    {
        return $user->can('attendance.confirm-dorm');
    }
}
