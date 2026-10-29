<?php

namespace App\Policies;

use App\Models\ProgramEvaluation;
use App\Models\User;

class ProgramEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('evaluation.manage') || $user->can('evaluation.results');
    }

    /** Lihat hasil survei (pimpinan read-only lewat evaluation.results). */
    public function view(User $user, ProgramEvaluation $evaluation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('evaluation.manage');
    }

    public function update(User $user, ProgramEvaluation $evaluation): bool
    {
        return $user->can('evaluation.manage');
    }

    /** Survei yang sudah ada jawabannya tidak dihapus agar hasilnya tetap tersimpan. */
    public function delete(User $user, ProgramEvaluation $evaluation): bool
    {
        return $user->can('evaluation.manage') && $evaluation->responses()->doesntExist();
    }

    /** Isi survei: sedang berjalan, ditujukan ke pengguna, dan belum diisi. */
    public function respondSurvey(User $user, ProgramEvaluation $evaluation): bool
    {
        return $evaluation->isOpen() && $evaluation->roleFor($user) !== null && ! $evaluation->hasResponded($user);
    }
}
