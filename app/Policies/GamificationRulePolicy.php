<?php

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\GamificationRule;
use App\Models\User;

class GamificationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gamification.manage');
    }

    /** Komponen tambahan langsung oleh pengelola gamifikasi. */
    public function create(User $user): bool
    {
        return $user->can('gamification.manage');
    }

    public function update(User $user, GamificationRule $rule): bool
    {
        return $user->can('gamification.manage');
    }

    public function delete(User $user, GamificationRule $rule): bool
    {
        return $rule->is_custom && $user->can('gamification.manage');
    }

    /** Pengajar mengajukan komponen tambahan (perlu persetujuan). */
    public function propose(User $user): bool
    {
        return $user->can('point.award');
    }

    public function review(User $user, GamificationRule $rule): bool
    {
        return $rule->status === ReviewStatus::Pending && $user->can('gamification.manage');
    }

    public function award(User $user, GamificationRule $rule): bool
    {
        return $rule->isAwardable() && $user->can('point.award');
    }
}
