<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\QuizPublishedNotification;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Buat/ubah quiz, susun soal dari bank, terbitkan, tutup, dan rilis hasil.
 */
class ManageQuiz
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(Classroom $classroom, User $teacher, array $data, ?Quiz $quiz = null): Quiz
    {
        $session = filled($data['learning_session_id'] ?? null) ? LearningSession::find($data['learning_session_id']) : null;

        if ($session && $session->classroom_id !== $classroom->id) {
            throw new InvalidArgumentException('Sesi tidak berada di kelas yang sama.');
        }

        $quiz ??= new Quiz(['status' => QuizStatus::Draft, 'teacher_id' => $teacher->id]);

        $quiz->fill([
            'classroom_id' => $classroom->id,
            'learning_session_id' => $session?->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'aspect' => $data['aspect'],
            'opens_at' => $data['opens_at'] ?: null,
            'closes_at' => $data['closes_at'] ?: null,
            'duration_minutes' => (int) $data['duration_minutes'],
            'max_attempts' => (int) $data['max_attempts'],
            'passing_score' => (float) $data['passing_score'],
            'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false),
            'shuffle_options' => (bool) ($data['shuffle_options'] ?? false),
            'result_visibility' => $data['result_visibility'],
            'reading_display' => $data['reading_display'],
            'score_scale' => (int) ($data['score_scale'] ?? 100),
        ])->save();

        return $quiz;
    }

    /**
     * @param  list<int>  $questionIds
     */
    public function addQuestions(Quiz $quiz, array $questionIds, ?string $section = null): int
    {
        $this->ensureEditable($quiz);
        $next = (int) $quiz->questions()->max('quiz_question.sort_order') + 1;
        $existing = $quiz->questions()->pluck('questions.id')->all();
        $added = 0;

        foreach (array_diff(array_map('intval', $questionIds), $existing) as $id) {
            $quiz->questions()->attach($id, ['sort_order' => $next++, 'section' => $section ?: null]);
            $added++;
        }

        return $added;
    }

    public function removeQuestion(Quiz $quiz, int $questionId): void
    {
        $this->ensureEditable($quiz);
        $quiz->questions()->detach($questionId);
    }

    public function move(Quiz $quiz, int $questionId, int $direction): void
    {
        $this->ensureEditable($quiz);
        $ids = $quiz->questions()->pluck('questions.id')->values();
        $index = $ids->search($questionId);
        $target = $index + $direction;

        if ($index === false || $target < 0 || $target >= $ids->count()) {
            return;
        }

        $swapped = $ids->all();
        [$swapped[$index], $swapped[$target]] = [$swapped[$target], $swapped[$index]];

        foreach ($swapped as $order => $id) {
            $quiz->questions()->updateExistingPivot($id, ['sort_order' => $order + 1]);
        }
    }

    public function updatePivot(Quiz $quiz, int $questionId, ?float $points, ?string $section): void
    {
        $this->ensureEditable($quiz);
        $quiz->questions()->updateExistingPivot($questionId, ['points' => $points, 'section' => $section ?: null]);
    }

    public function publish(Quiz $quiz): void
    {
        if (! $quiz->questions()->exists()) {
            throw new InvalidArgumentException('Tambahkan soal sebelum menerbitkan quiz.');
        }

        $quiz->update(['status' => QuizStatus::Published]);
        Notification::send($quiz->classroom->participants()->active()->get(), new QuizPublishedNotification($quiz));
    }

    public function close(Quiz $quiz): void
    {
        $quiz->update(['status' => QuizStatus::Closed]);
    }

    public function releaseResults(Quiz $quiz): void
    {
        $quiz->update(['results_released_at' => now()]);
    }

    /** Soal tidak boleh diubah setelah ada peserta yang mengerjakan. */
    protected function ensureEditable(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw new InvalidArgumentException('Soal tidak bisa diubah karena quiz sudah dikerjakan peserta.');
        }
    }
}
