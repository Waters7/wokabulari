<?php

namespace App\Events;

use App\Models\ProgramEvaluation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Responden selesai mengisi survei (untuk poin gamifikasi; isi jawaban tidak ikut).
 */
class EvaluationSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ProgramEvaluation $evaluation, public User $user) {}
}
