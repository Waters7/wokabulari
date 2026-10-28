<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;

class AssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assignment.manage') || $user->isParticipant();
    }

    public function view(User $user, Assignment $assignment): bool
    {
        if ($this->manage($user, $assignment) || $user->can('session.view-all')) {
            return true;
        }

        return $user->isParticipant()
            && $assignment->status !== AssignmentStatus::Draft
            && $assignment->classroom->participants()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('assignment.manage');
    }

    /** Pengajar kelas yang membuat/mengelola dan menilai tugas. */
    public function manage(User $user, Assignment $assignment): bool
    {
        return $user->can('assignment.manage')
            && ($assignment->teacher_id === $user->id || (bool) $assignment->classroom?->hasTeacher($user));
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $this->manage($user, $assignment);
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->manage($user, $assignment) && ! $assignment->submissions()->exists();
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $user->isParticipant() && $this->view($user, $assignment);
    }

    /** Peserta melihat pengumpulannya sendiri; pengajar kelas melihat semua. */
    public function viewSubmission(User $user, AssignmentSubmission $submission): bool
    {
        return $submission->user_id === $user->id || $this->manage($user, $submission->assignment);
    }
}
