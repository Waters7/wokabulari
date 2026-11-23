<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classroom.manage') || $user->can('participant.view') || $user->can('session.manage');
    }

    public function view(User $user, Classroom $classroom): bool
    {
        return $user->can('classroom.manage')
            || $user->can('session.view-all')
            || $classroom->hasTeacher($user);
    }

    public function create(User $user): bool
    {
        return $user->can('classroom.manage');
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->can('classroom.manage');
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->can('classroom.manage');
    }

    /** Rekap & rapor nilai kelas: pengajar kelas, atau yang boleh melihat semua sesi (admin, pimpinan). */
    public function viewScores(User $user, Classroom $classroom): bool
    {
        return $user->can('session.view-all') || ($user->can('quiz.manage') && $classroom->hasTeacher($user));
    }

    /** Pengajar kelas atau admin boleh mengelola kegiatan belajar kelas ini. */
    public function teach(User $user, Classroom $classroom): bool
    {
        return $classroom->hasTeacher($user)
            || ($user->can('session.manage') && $user->can('classroom.manage'));
    }
}
