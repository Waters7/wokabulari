<?php

namespace App\Policies;

use App\Models\GuardianInvitation;
use App\Models\User;

class GuardianInvitationPolicy
{
    /** Admin program mengelola wali & undangan. */
    public function viewAny(User $user): bool
    {
        return $user->can('guardian.verify');
    }

    public function create(User $user): bool
    {
        return $user->can('guardian.verify');
    }

    public function verify(User $user): bool
    {
        return $user->can('guardian.verify');
    }

    /** Peserta membagikan kode dari profilnya. */
    public function share(User $user): bool
    {
        return $user->isParticipant();
    }

    public function view(User $user, GuardianInvitation $invitation): bool
    {
        return $user->can('guardian.verify') || $invitation->participant_id === $user->id;
    }
}
