<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\PointAward;
use App\Models\User;

class PointAwardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('point.award') || $user->can('gamification.manage');
    }

    /** Pengajar hanya untuk kelas yang diajarnya; pengelola gamifikasi untuk semua kelas. */
    public function create(User $user, ?Classroom $classroom = null): bool
    {
        if (! $user->can('point.award')) {
            return false;
        }

        return $classroom === null || $user->can('gamification.manage') || $classroom->hasTeacher($user);
    }

    public function view(User $user, PointAward $award): bool
    {
        return $user->can('gamification.manage') || $award->awarded_by === $user->id || (bool) $award->classroom?->hasTeacher($user);
    }
}
