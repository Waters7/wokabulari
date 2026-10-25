<?php

namespace App\Policies;

use App\Models\HealthReport;
use App\Models\User;

/**
 * Data kesehatan rahasia (SPEC 3.18): detail hanya untuk peserta ybs., pengelola asrama, tim kerja terkait,
 * super admin, dan wali peserta tersebut. Pimpinan hanya statistik.
 */
class HealthReportPolicy
{
    /** Peserta melapor untuk diri sendiri/teman sekamar; petugas atas nama peserta. */
    public function create(User $user): bool
    {
        return $user->isParticipant() || $user->can('health.handle');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('health.view-detail');
    }

    public function view(User $user, HealthReport $report): bool
    {
        return $report->user_id === $user->id
            || $user->can('health.view-detail')
            || $user->isGuardianOf($report->user);
    }

    public function handle(User $user, ?HealthReport $report = null): bool
    {
        return $user->can('health.handle');
    }

    public function viewStats(User $user): bool
    {
        return $user->can('health.view-stats') || $user->can('health.view-detail');
    }
}
