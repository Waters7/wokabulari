<?php

namespace App\Policies;

use App\Models\TeacherRating;
use App\Models\User;

class TeacherRatingPolicy
{
    public function create(User $user): bool
    {
        return $user->isParticipant();
    }

    /** Pengajar melihat rating untuk dirinya; admin & pimpinan melihat semua (dengan nama). */
    public function view(User $user, TeacherRating $rating): bool
    {
        return $rating->teacher_id === $user->id || $rating->user_id === $user->id || $user->can('rating.view-names');
    }
}
