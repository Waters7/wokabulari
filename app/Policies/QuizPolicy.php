<?php

namespace App\Policies;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quiz.manage') || $user->isParticipant();
    }

    public function view(User $user, Quiz $quiz): bool
    {
        if ($this->manage($user, $quiz) || $user->can('session.view-all')) {
            return true;
        }

        return $user->isParticipant()
            && $quiz->status !== QuizStatus::Draft
            && $quiz->classroom->participants()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('quiz.manage');
    }

    /** Pengajar kelas yang membuat/mengelola quiz dan menilai jawaban. */
    public function manage(User $user, Quiz $quiz): bool
    {
        return $user->can('quiz.manage') && ($quiz->teacher_id === $user->id || $quiz->classroom?->teacher_id === $user->id);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->manage($user, $quiz);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->manage($user, $quiz) && ! $quiz->attempts()->exists();
    }

    public function take(User $user, Quiz $quiz): bool
    {
        return $user->isParticipant() && $this->view($user, $quiz);
    }

    public function viewAttempt(User $user, QuizAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id || $this->manage($user, $attempt->quiz);
    }
}
