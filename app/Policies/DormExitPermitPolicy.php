<?php

namespace App\Policies;

use App\Models\DormExitPermit;
use App\Models\User;

class DormExitPermitPolicy
{
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    public function viewAny(User $user): bool
    {
        return $user->can('dorm-exit.approve');
    }

    public function view(User $user, DormExitPermit $permit): bool
    {
        return $permit->user_id === $user->id || $user->can('dorm-exit.approve') || $user->isGuardianOf($permit->user);
    }

    public function guardianDecide(User $user, DormExitPermit $permit): bool
    {
        return $user->isGuardian() && $user->isGuardianOf($permit->user);
    }

    public function decide(User $user, ?DormExitPermit $permit = null): bool
    {
        return $user->can('dorm-exit.approve');
    }

    public function scan(User $user): bool
    {
        return $user->can('dorm-exit.approve');
    }

    public function cancel(User $user, DormExitPermit $permit): bool
    {
        return $permit->user_id === $user->id;
    }
}
