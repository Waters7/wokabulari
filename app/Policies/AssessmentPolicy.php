<?php

namespace App\Policies;

use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\User;

class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assessment.manage') || $user->can('session.view-all');
    }

    public function view(User $user, Assessment $assessment): bool
    {
        if ($assessment->user_id === $user->id) {
            return $assessment->isPublished();
        }

        return $user->can('session.view-all') || $this->manage($user, $assessment->classroom);
    }

    /** Pengajar kelas (dengan permission assessment.manage) mengelola penilaian kelasnya. */
    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->can('assessment.manage') && $classroom->teacher_id === $user->id;
    }
}
