<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.manage') || $user->can('participant.view');
    }

    public function view(User $user, User $target): bool
    {
        if ($user->is($target) || $user->can('user.manage')) {
            return true;
        }

        if ($target->isParticipant()) {
            return $user->can('participant.view') && ($user->can('participant.manage') || $user->can('dashboard.leader') || $this->teaches($user, $target));
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage') || $user->can('participant.manage');
    }

    public function update(User $user, User $target): bool
    {
        if ($user->can('user.manage')) {
            return true;
        }

        return $target->isParticipant() && $user->can('participant.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->update($user, $target);
    }

    /** Pengguna mengisi email login sendiri; email super admin dikelola lewat server. */
    public function updateLoginEmail(User $user, User $target): bool
    {
        return $user->is($target) && ! $target->isSuperAdmin();
    }

    /** Peserta melengkapi biodatanya sendiri. */
    public function updateBiodata(User $user, User $target): bool
    {
        return $user->is($target) && $target->isParticipant();
    }

    /** Reset/kelola wajah referensi peserta. */
    public function manageFace(User $user, User $target): bool
    {
        return $user->is($target) || ($target->isParticipant() && $user->can('participant.manage'));
    }

    protected function teaches(User $teacher, User $participant): bool
    {
        return $participant->classrooms()->taughtBy($teacher)->exists();
    }
}
