<?php

namespace App\Policies;

use App\Enums\SessionStatus;
use App\Models\LearningSession;
use App\Models\User;

class LearningSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('session.manage') || $user->can('session.view-all');
    }

    public function view(User $user, LearningSession $session): bool
    {
        if ($user->can('session.view-all') || $this->teaches($user, $session)) {
            return true;
        }

        return $user->isParticipant() && $session->classroom->participants()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('session.manage');
    }

    /** Kelola sesi: pengajar kelas atau admin program. */
    public function update(User $user, LearningSession $session): bool
    {
        return $user->can('session.manage') && ($this->teaches($user, $session) || $user->can('classroom.manage'));
    }

    public function delete(User $user, LearningSession $session): bool
    {
        return $this->update($user, $session) && $session->status === SessionStatus::Scheduled;
    }

    /** Input komponen, skor, dan catatan sesi. */
    public function assess(User $user, LearningSession $session): bool
    {
        return $user->can('assessment.manage') && $this->teaches($user, $session);
    }

    protected function teaches(User $user, LearningSession $session): bool
    {
        return $session->teacher_id === $user->id || $session->classroom?->teacher_id === $user->id;
    }
}
