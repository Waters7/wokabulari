<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\Quiz;
use App\Models\User;
use App\Notifications\QuizPublishedNotification;
use Illuminate\Support\Facades\DB;
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

    /**
     * Bagi rata total poin ke semua soal: tiap jawaban benar bernilai sama dan jumlahnya tepat = total
     * (sisa pembulatan sen dibagikan ke soal-soal awal).
     *
     * @return float|null poin per soal (pembulatan ke bawah), null bila belum ada soal
     */
    public function distributePoints(Quiz $quiz, float $total): ?float
    {
        $this->ensureEditable($quiz);
        $ids = $quiz->questions()->pluck('questions.id')->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $cents = (int) round($total * 100);
        [$base, $extra] = [intdiv($cents, $ids->count()), $cents % $ids->count()];

        if ($base < 1) {
            throw new InvalidArgumentException('Total poin terlalu kecil untuk '.$ids->count().' soal.');
        }

        DB::transaction(function () use ($quiz, $ids, $base, $extra) {
            foreach ($ids as $i => $id) {
                $quiz->questions()->updateExistingPivot($id, ['points' => ($base + ($i < $extra ? 1 : 0)) / 100]);
            }
        });

        return $base / 100;
    }

    /**
     * Poin per jenis soal (mis. pilihan ganda 2, menjodohkan 10). Jenis yang dikosongkan tidak diubah.
     *
     * @param  array<string, mixed>  $pointsByType  nilai jenis soal => poin
     * @return int jumlah soal yang diubah
     */
    public function setPointsByType(Quiz $quiz, array $pointsByType): int
    {
        $this->ensureEditable($quiz);
        $changed = 0;

        DB::transaction(function () use ($quiz, $pointsByType, &$changed) {
            foreach ($quiz->questions()->get() as $question) {
                $points = $pointsByType[$question->type->value] ?? null;
                if (! is_numeric($points)) {
                    continue;
                }

                $quiz->questions()->updateExistingPivot($question->id, ['points' => round((float) $points, 2)]);
                $changed++;
            }
        });

        return $changed;
    }

    /** Kembalikan semua poin ke nilai di bank soal. */
    public function resetPoints(Quiz $quiz): void
    {
        $this->ensureEditable($quiz);
        $quiz->questions()->newPivotQuery()->update(['points' => null]);
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

    /**
     * Hapus quiz yang belum dikerjakan siapa pun (mis. tidak jadi terbit). Soal tetap ada di bank soal.
     */
    public function delete(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw new InvalidArgumentException('Quiz sudah dikerjakan peserta sehingga tidak bisa dihapus. Tutup quiz bila tidak dipakai lagi.');
        }

        $quiz->delete();
    }

    /** Soal tidak boleh diubah setelah ada peserta yang mengerjakan. */
    protected function ensureEditable(Quiz $quiz): void
    {
        if ($quiz->attempts()->exists()) {
            throw new InvalidArgumentException('Soal tidak bisa diubah karena quiz sudah dikerjakan peserta.');
        }
    }
}
