<?php

namespace App\Actions\Quizzes;

use App\Actions\Assessments\SyncSourceComponent;
use App\Enums\AttemptStatus;
use App\Enums\ComponentSource;
use App\Enums\QuestionType;
use App\Events\QuizCompleted;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\QuizGrader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Siklus attempt quiz: mulai → simpan jawaban (autosave) → kumpul (manual/otomatis) → nilai.
 * Validasi waktu & sesi dilakukan di server; browser hanya pelengkap.
 */
class QuizAttemptService
{
    /** Toleransi jaringan saat menyimpan tepat di detik terakhir. */
    protected const GRACE_SECONDS = 15;

    public function __construct(
        protected QuizGrader $grader,
        protected SyncSourceComponent $components,
    ) {}

    public function start(Quiz $quiz, User $user, string $sessionId, ?string $ip = null, ?string $device = null): QuizAttempt
    {
        if (! $quiz->classroom->participants()->whereKey($user->id)->exists()) {
            throw new InvalidArgumentException('Ngana tidak terdaftar di kelas quiz ini.');
        }

        $active = $quiz->attempts()->where('user_id', $user->id)->where('status', AttemptStatus::InProgress)->latest('id')->first();

        if ($active && $active->expires_at->isPast()) {
            $this->submit($active, auto: true);
            $active = null;
        }

        // Satu attempt aktif per peserta; membuka dari sesi lain mengambil alih sesi tersebut.
        if ($active) {
            $active->update(['session_id' => $sessionId, 'ip' => $ip]);

            return $active;
        }

        if (! $quiz->isOpen()) {
            throw new InvalidArgumentException($quiz->opens_at?->isFuture() ? 'Quiz dibuka '.tanggal_jam($quiz->opens_at).'.' : 'Quiz sudah ditutup.');
        }

        $used = $quiz->attempts()->where('user_id', $user->id)->count();
        if ($used >= $quiz->max_attempts) {
            throw new InvalidArgumentException('Kesempatan mengerjakan sudah habis ('.$quiz->max_attempts.'×).');
        }

        $questions = $quiz->questions()->with('options')->get();
        if ($questions->isEmpty()) {
            throw new InvalidArgumentException('Quiz belum memiliki soal.');
        }

        $order = $questions->pluck('id');
        if ($quiz->shuffle_questions) {
            $order = $order->shuffle();
        }

        $optionOrder = [];
        foreach ($questions as $question) {
            $ids = $question->options->pluck('id');
            if ($question->type === QuestionType::Matching || ($quiz->shuffle_options && in_array($question->type, [QuestionType::MultipleChoice, QuestionType::Listening], true))) {
                $ids = $ids->shuffle();
            }
            $optionOrder[$question->id] = $ids->values()->all();
        }

        $expires = now()->addMinutes($quiz->duration_minutes);
        if ($quiz->closes_at && $quiz->closes_at->lt($expires)) {
            $expires = $quiz->closes_at;
        }

        return $quiz->attempts()->create([
            'user_id' => $user->id,
            'started_at' => now(),
            'expires_at' => $expires,
            'status' => AttemptStatus::InProgress,
            'session_id' => $sessionId,
            'ip' => $ip,
            'device_info' => $device ? mb_substr($device, 0, 250) : null,
            'question_order' => $order->values()->all(),
            'option_order' => $optionOrder,
        ]);
    }

    /**
     * Simpan jawaban (autosave tiap 10 detik & saat kumpul).
     *
     * @param  array<int|string, mixed>  $answers  question_id => jawaban
     */
    public function saveAnswers(QuizAttempt $attempt, array $answers, string $sessionId): void
    {
        $this->ensureWritable($attempt, $sessionId);

        $questions = $attempt->quiz->questions()->with('options')->get()->keyBy('id');

        foreach ($answers as $questionId => $value) {
            $question = $questions->get((int) $questionId);

            if (! $question || $question->type === QuestionType::Speaking) {
                continue;
            }

            $clean = $this->sanitize($question, $value);

            QuizAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id],
                ['answer' => $clean],
            );
        }
    }

    public function saveAudio(QuizAttempt $attempt, Question $question, UploadedFile $audio, string $sessionId): void
    {
        $this->ensureWritable($attempt, $sessionId);

        if ($question->type !== QuestionType::Speaking || ! in_array($question->id, $attempt->question_order ?? [], true)) {
            throw new InvalidArgumentException('Soal tidak valid.');
        }

        $answer = QuizAnswer::query()->firstOrNew(['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id]);
        if ($answer->audio_path) {
            Storage::disk('private')->delete($answer->audio_path);
        }

        $answer->audio_path = $audio->storeAs("quiz-audio/{$attempt->quiz_id}/{$attempt->id}", Str::uuid().'.'.($audio->extension() ?: 'webm'), 'private');
        $answer->answer = ['recorded' => true];
        $answer->save();
    }

    public function recordTabSwitch(QuizAttempt $attempt, string $sessionId): int
    {
        if ($attempt->isInProgress() && $attempt->session_id === $sessionId) {
            $attempt->increment('tab_switch_count');
        }

        return $attempt->tab_switch_count;
    }

    public function submit(QuizAttempt $attempt, bool $auto = false): QuizAttempt
    {
        if (! $attempt->isInProgress()) {
            return $attempt;
        }

        DB::transaction(function () use ($attempt, $auto) {
            $quiz = $attempt->quiz;
            $questions = $quiz->questions()->with('options')->get();
            $answers = $attempt->answers()->get()->keyBy('question_id');
            $pending = false;

            foreach ($questions as $question) {
                $answer = $answers->get($question->id) ?? new QuizAnswer(['quiz_attempt_id' => $attempt->id, 'question_id' => $question->id]);
                $result = $this->grader->grade($question, $answer->answer, $quiz->pointsFor($question), filled($answer->audio_path));

                $answer->fill($result)->save();
                $pending = $pending || $result['score'] === null;
            }

            $attempt->fill([
                'submitted_at' => now(),
                'auto_submitted' => $auto,
                'status' => $pending ? AttemptStatus::NeedsGrading : AttemptStatus::Submitted,
            ])->save();
        });

        $attempt->refresh();

        return $attempt->status === AttemptStatus::NeedsGrading ? $this->recalculate($attempt) : $this->finalize($attempt);
    }

    /** Hitung skor sementara (tanpa soal yang menunggu penilaian). */
    public function recalculate(QuizAttempt $attempt): QuizAttempt
    {
        $quiz = $attempt->quiz;
        $total = $quiz->questions()->get()->sum(fn ($q) => $quiz->pointsFor($q));
        $attempt->update(['score' => (float) $attempt->answers()->sum('score'), 'max_score' => $total]);

        return $attempt;
    }

    /** Semua jawaban sudah bernilai: tetapkan skor akhir, kirim event, sinkron komponen sesi. */
    public function finalize(QuizAttempt $attempt): QuizAttempt
    {
        $quiz = $attempt->quiz;
        $total = (float) $quiz->questions()->get()->sum(fn ($q) => $quiz->pointsFor($q));
        $score = (float) $attempt->answers()->sum('score');

        $attempt->update([
            'score' => round($score, 2),
            'max_score' => $total,
            'percentage' => $total > 0 ? round($score / $total * 100, 2) : 0,
            'status' => AttemptStatus::Graded,
        ]);

        if ($quiz->session) {
            $component = $this->components->ensure($quiz->session, ComponentSource::Quiz, $quiz->id, 'Quiz: '.Str::limit($quiz->title, 60), $quiz->aspect);
            $best = $quiz->attempts()->where('user_id', $attempt->user_id)->where('status', AttemptStatus::Graded)->max('percentage');
            $this->components->setScore($component, $attempt->user_id, $best !== null ? (float) $best : null);
        }

        event(new QuizCompleted($attempt));

        return $attempt;
    }

    /** Dipakai scheduler: kumpulkan otomatis attempt yang waktunya habis. */
    public function submitExpired(): int
    {
        $count = 0;

        QuizAttempt::query()->where('status', AttemptStatus::InProgress)->where('expires_at', '<=', now())
            ->each(function (QuizAttempt $attempt) use (&$count) {
                $this->submit($attempt, auto: true);
                $count++;
            });

        return $count;
    }

    protected function ensureWritable(QuizAttempt $attempt, string $sessionId): void
    {
        if (! $attempt->isInProgress()) {
            throw new InvalidArgumentException('Attempt sudah dikumpulkan.');
        }

        if ($attempt->expires_at->copy()->addSeconds(self::GRACE_SECONDS)->isPast()) {
            throw new InvalidArgumentException('Waktu pengerjaan sudah habis.');
        }

        if ($attempt->session_id !== $sessionId) {
            throw new InvalidArgumentException('Quiz sedang dibuka di perangkat atau sesi lain.');
        }
    }

    /**
     * Bersihkan jawaban dari browser sesuai tipe soal (opsi harus milik soal tersebut).
     */
    protected function sanitize(Question $question, mixed $value): ?array
    {
        $type = $question->type;

        if ($type->usesChoice()) {
            $option = (int) (is_array($value) ? ($value['option'] ?? 0) : $value);

            return $question->options->contains('id', $option) ? ['option' => $option] : null;
        }

        if ($type === QuestionType::Matching) {
            $pairs = collect(is_array($value) ? ($value['pairs'] ?? $value) : [])
                ->filter(fn ($v, $k) => $question->options->contains('id', (int) $k) && is_string($v))
                ->map(fn ($v) => mb_substr($v, 0, 255))
                ->all();

            return ['pairs' => $pairs];
        }

        $text = is_array($value) ? ($value['text'] ?? '') : (string) $value;

        return ['text' => mb_substr((string) $text, 0, 10000)];
    }
}
