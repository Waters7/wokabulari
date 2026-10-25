<?php

namespace App\Policies;

use App\Models\PointTransaction;
use App\Models\User;

class PointTransactionPolicy
{
    /** Peserta melihat riwayat poinnya sendiri. */
    public function viewAny(User $user): bool
    {
        return $user->isParticipant();
    }

    public function view(User $user, PointTransaction $transaction): bool
    {
        return $transaction->user_id === $user->id || $user->can('gamification.manage') || $user->isGuardianOf($transaction->user);
    }

    /** Penyesuaian manual oleh admin gamifikasi. */
    public function create(User $user): bool
    {
        return $user->can('gamification.manage');
    }
}
