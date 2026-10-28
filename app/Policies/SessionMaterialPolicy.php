<?php

namespace App\Policies;

use App\Models\SessionMaterial;
use App\Models\User;

class SessionMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('material.manage') || $user->isParticipant();
    }

    public function view(User $user, SessionMaterial $material): bool
    {
        if ($user->can('material.manage') || $user->can('session.view-all')) {
            return true;
        }

        if (! $user->isParticipant() || ! $material->session) {
            return false;
        }

        return $material->isVisibleNow()
            && $material->session->classroom->participants()->whereKey($user->id)->exists();
    }

    public function download(User $user, SessionMaterial $material): bool
    {
        if ($user->can('material.manage')) {
            return true;
        }

        return $material->is_downloadable && $this->view($user, $material);
    }

    public function create(User $user): bool
    {
        return $user->can('material.manage');
    }

    public function update(User $user, SessionMaterial $material): bool
    {
        return $user->can('material.manage')
            && ($material->teacher_id === $user->id || $user->can('classroom.manage') || (bool) $material->session?->classroom?->hasTeacher($user));
    }

    public function delete(User $user, SessionMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
