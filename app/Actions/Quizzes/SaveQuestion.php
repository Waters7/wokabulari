<?php

namespace App\Actions\Quizzes;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Simpan soal beserta opsinya sesuai tipe.
 *
 * Format $data:
 * - type, body, reading, points, explanation
 * - options: list<{body, reading?, is_correct?, match_key?}> (pilihan ganda, menyimak, menjodohkan)
 * - accepted_answers: list<string> (isian singkat)
 * - correct_bool: bool (benar/salah), correct_tone: 1–4 (nada)
 */
class SaveQuestion
{
    public const TONES = [
        1 => 'Nada 1 (datar) — ā',
        2 => 'Nada 2 (naik) — á',
        3 => 'Nada 3 (turun-naik) — ǎ',
        4 => 'Nada 4 (turun) — à',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(QuestionBank $bank, array $data, ?Question $question = null, ?UploadedFile $media = null, bool $allowMissingMedia = false): Question
    {
        $type = $data['type'] instanceof QuestionType ? $data['type'] : QuestionType::from($data['type']);
        $options = $this->options($type, $data);

        if ($type === QuestionType::Listening && ! $allowMissingMedia && ! $media && ! $question?->media_path) {
            throw new InvalidArgumentException('Soal menyimak wajib memiliki audio.');
        }

        return DB::transaction(function () use ($bank, $data, $question, $media, $type, $options) {
            $question ??= new Question(['question_bank_id' => $bank->id]);
            $question->fill([
                'type' => $type,
                'body' => trim($data['body']),
                'reading' => filled($data['reading'] ?? null) ? trim($data['reading']) : null,
                'points' => (float) ($data['points'] ?? 1),
                'explanation' => $data['explanation'] ?? null,
            ]);

            if ($media) {
                if ($question->media_path) {
                    Storage::disk('private')->delete($question->media_path);
                }
                $question->media_path = $media->storeAs('question-media/'.$bank->id, Str::uuid().'.'.($media->extension() ?: 'mp3'), 'private');
            }

            $question->save();
            $question->options()->delete();

            foreach ($options as $index => $option) {
                $question->options()->create($option + ['sort_order' => $index]);
            }

            return $question->load('options');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function options(QuestionType $type, array $data): array
    {
        $clean = fn (?string $v) => filled($v) ? trim($v) : null;

        return match ($type) {
            QuestionType::MultipleChoice, QuestionType::Listening => $this->choices($data['options'] ?? [], $clean),
            QuestionType::TrueFalse => [
                ['body' => 'Benar', 'is_correct' => (bool) ($data['correct_bool'] ?? true)],
                ['body' => 'Salah', 'is_correct' => ! (bool) ($data['correct_bool'] ?? true)],
            ],
            QuestionType::Tone => $this->tones((int) ($data['correct_tone'] ?? 0)),
            QuestionType::ShortAnswer => $this->accepted($data['accepted_answers'] ?? [], $clean),
            QuestionType::Matching => $this->pairs($data['options'] ?? [], $clean),
            QuestionType::Essay, QuestionType::Speaking => [],
        };
    }

    protected function choices(array $options, callable $clean): array
    {
        $options = collect($options)
            ->map(fn ($o) => ['body' => $clean($o['body'] ?? null), 'reading' => $clean($o['reading'] ?? null), 'is_correct' => (bool) ($o['is_correct'] ?? false)])
            ->filter(fn ($o) => $o['body'] !== null)
            ->values();

        if ($options->count() < 2) {
            throw new InvalidArgumentException('Pilihan ganda butuh minimal 2 opsi.');
        }

        if ($options->where('is_correct', true)->count() !== 1) {
            throw new InvalidArgumentException('Tandai tepat satu opsi sebagai jawaban benar.');
        }

        return $options->all();
    }

    protected function tones(int $correct): array
    {
        if ($correct < 1 || $correct > 4) {
            throw new InvalidArgumentException('Pilih nada yang benar (1–4).');
        }

        return collect(self::TONES)->map(fn ($label, $tone) => ['body' => (string) $tone, 'reading' => $label, 'is_correct' => $tone === $correct])->values()->all();
    }

    protected function accepted(array $answers, callable $clean): array
    {
        $answers = collect($answers)->map($clean)->filter()->unique()->values();

        if ($answers->isEmpty()) {
            throw new InvalidArgumentException('Isi minimal satu jawaban yang diterima.');
        }

        return $answers->map(fn ($a) => ['body' => $a, 'is_correct' => true])->all();
    }

    protected function pairs(array $options, callable $clean): array
    {
        $pairs = collect($options)
            ->map(fn ($o) => ['body' => $clean($o['body'] ?? null), 'reading' => $clean($o['reading'] ?? null), 'match_key' => $clean($o['match_key'] ?? null), 'is_correct' => true])
            ->filter(fn ($o) => $o['body'] !== null && $o['match_key'] !== null)
            ->values();

        if ($pairs->count() < 2) {
            throw new InvalidArgumentException('Soal menjodohkan butuh minimal 2 pasangan.');
        }

        return $pairs->all();
    }
}
