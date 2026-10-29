<?php

namespace App\Actions\Evaluations;

use App\Enums\EvaluationQuestionType;
use App\Events\EvaluationSubmitted;
use App\Models\Classroom;
use App\Models\EvaluationResponse;
use App\Models\ProgramEvaluation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Simpan jawaban survei. Survei anonim hanya menyimpan hash responden (tanpa user_id).
 */
class SubmitEvaluation
{
    /**
     * @param  array<int|string, mixed>  $answers  evaluation_question_id => nilai (sudah divalidasi)
     */
    public function handle(ProgramEvaluation $evaluation, User $user, array $answers): EvaluationResponse
    {
        $role = $evaluation->roleFor($user);

        if ($role === null) {
            throw new InvalidArgumentException('Survei ini tidak ditujukan untuk ngana.');
        }

        if (! $evaluation->isOpen()) {
            throw new InvalidArgumentException($evaluation->isClosed() ? 'Survei sudah ditutup.' : 'Survei belum dibuka.');
        }

        if ($evaluation->hasResponded($user)) {
            throw new InvalidArgumentException('Ngana so isi survei ini. Makasih!');
        }

        $questions = $evaluation->loadMissing('questions')->questionsFor($role);

        try {
            $response = DB::transaction(function () use ($evaluation, $user, $role, $answers, $questions) {
                $response = $evaluation->responses()->create([
                    'user_id' => $evaluation->is_anonymous ? null : $user->id,
                    'respondent_hash' => $evaluation->respondentHash($user),
                    'respondent_role' => $role,
                    'batch_id' => $this->batchOf($evaluation, $user, $role),
                    'submitted_at' => now(),
                ]);

                foreach ($questions as $question) {
                    $value = $answers[$question->id] ?? null;

                    if (blank($value)) {
                        continue;
                    }

                    $response->answers()->create([
                        'evaluation_question_id' => $question->id,
                        'value' => trim((string) $value),
                        'score' => $question->type === EvaluationQuestionType::Likert ? (int) $value : null,
                    ]);
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Ngana so isi survei ini. Makasih!');
        }

        event(new EvaluationSubmitted($evaluation, $user));

        return $response;
    }

    /** Angkatan responden di antara angkatan sasaran (untuk perbandingan antarangkatan). */
    protected function batchOf(ProgramEvaluation $evaluation, User $user, string $role): ?int
    {
        $batchIds = $evaluation->batches()->pluck('batches.id');
        $classes = $role === ProgramEvaluation::ROLE_PARTICIPANT
            ? $user->classrooms()->whereIn('batch_id', $batchIds)
            : Classroom::query()->taughtBy($user)->whereIn('batch_id', $batchIds);

        return $classes->value('batch_id');
    }
}
