<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.approve') || $user->can('attendance.view-all');
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id
            || $user->can('attendance.view-all')
            || $this->review($user, $leave);
    }

    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    /** Peserta boleh membatalkan pengajuan yang masih menunggu. */
    public function delete(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id && $leave->status === ReviewStatus::Pending;
    }

    /** Disetujui pengajar kelas peserta atau admin program. */
    public function review(User $user, LeaveRequest $leave): bool
    {
        if (! $user->can('leave.approve')) {
            return false;
        }

        return $user->can('participant.manage')
            || $leave->user->classrooms()->where('teacher_id', $user->id)->exists();
    }
}
