<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;

/**
 * Bank soal dapat dipakai ulang semua pengajar; hanya pemiliknya yang mengubah.
 */
class QuestionBankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quiz.manage');
    }

    public function view(User $user, QuestionBank $bank): bool
    {
        return $user->can('quiz.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('quiz.manage');
    }

    public function update(User $user, QuestionBank $bank): bool
    {
        return $user->can('quiz.manage') && ($bank->teacher_id === $user->id || $bank->teacher_id === null);
    }

    public function delete(User $user, QuestionBank $bank): bool
    {
        return $this->update($user, $bank) && ! $bank->questions()->whereHas('quizzes')->exists();
    }
}
