<?php

namespace App\Actions\Quizzes;

use App\Enums\AttemptStatus;
use App\Models\QuizAnswer;
use App\Models\User;
use InvalidArgumentException;

/**
 * Pengajar menilai jawaban esai/berbicara. Bila tidak ada lagi yang menunggu, attempt difinalkan.
 */
class GradeQuizAnswer
{
    public function __construct(protected QuizAttemptService $attempts) {}

    public function handle(QuizAnswer $answer, User $grader, float $score, ?string $feedback = null): QuizAnswer
    {
        $attempt = $answer->attempt;
        $points = $attempt->quiz->pointsFor($attempt->quiz->questions()->whereKey($answer->question_id)->firstOrFail());

        if ($score < 0 || $score > $points) {
            throw new InvalidArgumentException('Nilai harus 0–'.angka($points, 1).'.');
        }

        $answer->update([
            'score' => round($score, 2),
            'is_correct' => $score >= $points,
            'teacher_feedback' => $feedback,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ]);

        if ($attempt->status === AttemptStatus::NeedsGrading && ! $attempt->answers()->whereNull('score')->exists()) {
            $this->attempts->finalize($attempt->fresh());
        } elseif ($attempt->status === AttemptStatus::Graded) {
            $this->attempts->finalize($attempt->fresh());
        } else {
            $this->attempts->recalculate($attempt->fresh());
        }

        return $answer;
    }
}
