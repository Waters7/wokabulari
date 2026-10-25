<?php

namespace App\Policies;

use App\Models\DormRequest;
use App\Models\User;

class DormRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    public function viewAny(User $user): bool
    {
        return $user->can('dorm-request.handle');
    }

    public function view(User $user, DormRequest $request): bool
    {
        return $request->user_id === $user->id || $user->can('dorm-request.handle');
    }

    public function update(User $user, DormRequest $request): bool
    {
        return $user->can('dorm-request.handle');
    }
}
