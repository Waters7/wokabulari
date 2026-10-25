<?php

namespace App\Policies;

use App\Models\GamificationRule;
use App\Models\User;

class GamificationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gamification.manage');
    }

    public function update(User $user, GamificationRule $rule): bool
    {
        return $user->can('gamification.manage');
    }
}
