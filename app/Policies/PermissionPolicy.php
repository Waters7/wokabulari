<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy dasar untuk data master: kelola butuh permission "manage", lihat cukup permission "view".
 * Pimpinan hanya mendapat permission "view" sehingga selalu read-only.
 */
abstract class PermissionPolicy
{
    protected string $manage;

    protected ?string $view = null;

    public function viewAny(User $user): bool
    {
        return $user->can($this->manage) || ($this->view !== null && $user->can($this->view));
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can($this->manage);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->manage);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->manage);
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->manage);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }
}
