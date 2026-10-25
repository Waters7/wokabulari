<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /** Semua pengguna yang masuk bisa membaca pengumuman yang ditujukan padanya. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement)
            || Announcement::query()->visibleTo($user)->whereKey($announcement->id)->exists();
    }

    /** Tim kerja, pengajar, pengelola asrama, admin program (pimpinan read-only). */
    public function create(User $user): bool
    {
        return $user->can('announcement.manage');
    }

    /** Pembuat, atau admin program untuk semua pengumuman. */
    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can('announcement.manage')
            && ($announcement->created_by === $user->id || $user->hasRole(Role::ProgramAdmin->value));
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
