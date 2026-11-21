<?php

namespace App\Actions\Quizzes;

use App\Actions\Assessments\SyncSourceComponent;
use App\Enums\AttemptStatus;
use App\Enums\ComponentSource;
use App\Models\QuizAttempt;
use App\Models\SessionAssessmentComponent;
use App\Models\User;
use App\Notifications\QuizRetakeNotification;
use Illuminate\Support\Facades\DB;

/**
 * Reset pengerjaan quiz seorang peserta (mis. terkumpul tidak sengaja) agar ia boleh mengerjakan ulang.
 * Attempt disembunyikan (soft delete), tidak dihapus permanen; nilai komponen sesi dihitung ulang.
 */
class ResetQuizAttempt
{
    public function __construct(protected SyncSourceComponent $components) {}

    public function handle(QuizAttempt $attempt, User $by): void
    {
        $quiz = $attempt->quiz;

        DB::transaction(function () use ($attempt, $quiz, $by) {
            activity('quiz')->causedBy($by)->performedOn($quiz)->withProperties([
                'attempt_id' => $attempt->id,
                'peserta' => $attempt->user->name,
                'status' => $attempt->status->value,
                'skor' => $attempt->score,
                'persen' => $attempt->percentage,
                'mulai' => $attempt->started_at?->toDateTimeString(),
                'dikumpulkan' => $attempt->submitted_at?->toDateTimeString(),
                'otomatis' => $attempt->auto_submitted,
            ])->log('Reset pengerjaan quiz peserta');

            $attempt->delete();

            // Nilai komponen sesi memakai nilai terbaik dari attempt yang tersisa.
            $component = $quiz->session ? SessionAssessmentComponent::query()->where('learning_session_id', $quiz->session->id)
                ->where('source', ComponentSource::Quiz)->where('source_id', $quiz->id)->first() : null;
            if ($component) {
                $best = $quiz->attempts()->where('user_id', $attempt->user_id)->where('status', AttemptStatus::Graded)->max('percentage');
                $this->components->setScore($component, $attempt->user_id, $best !== null ? (float) $best : null);
            }
        });

        $attempt->user->notify(new QuizRetakeNotification($quiz));
    }
}
