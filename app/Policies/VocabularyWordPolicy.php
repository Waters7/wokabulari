<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VocabularyWord;

/** Bank kosakata dikelola pengajar & admin program (word.manage). */
class VocabularyWordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('word.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('word.manage');
    }

    public function update(User $user, VocabularyWord $word): bool
    {
        return $user->can('word.manage');
    }

    public function delete(User $user, VocabularyWord $word): bool
    {
        return $user->can('word.manage');
    }
}
