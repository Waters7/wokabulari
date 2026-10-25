<?php

namespace App\Actions\Quizzes;

use App\Enums\QuestionType;
use App\Models\QuestionBank;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Import soal dari Excel. Kolom: tipe, soal, bacaan, opsi_a..opsi_e, jawaban, poin, pembahasan.
 *
 * Kolom "jawaban": huruf opsi (pilihan ganda/menyimak), benar/salah, nada 1–4,
 * jawaban isian dipisah "|", atau pasangan menjodohkan "kiri=kanan|kiri=kanan".
 * Soal menyimak/berbicara dapat diimport tanpa audio, lalu audionya ditambahkan dari halaman bank soal.
 */
class ImportQuestions
{
    public function __construct(protected SaveQuestion $save) {}

    /**
     * @param  iterable<array<string, mixed>|Collection<string, mixed>>  $rows
     * @return array{created: int, errors: list<string>}
     */
    public function handle(QuestionBank $bank, iterable $rows): array
    {
        $created = 0;
        $errors = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $row = collect($row instanceof Collection ? $row->all() : $row)->mapWithKeys(fn ($v, $k) => [Str::slug((string) $k, '_') => is_string($v) ? trim($v) : $v]);

            if (blank($row->get('soal'))) {
                continue;
            }

            $type = QuestionType::fromImport((string) $row->get('tipe', 'pg'));
            if (! $type) {
                $errors[] = "Baris {$line}: tipe soal \"{$row->get('tipe')}\" tidak dikenal.";

                continue;
            }

            try {
                $this->save->handle($bank, $this->data($type, $row), null, null, allowMissingMedia: true);
                $created++;
            } catch (InvalidArgumentException $e) {
                $errors[] = "Baris {$line}: {$e->getMessage()}";
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(QuestionType $type, Collection $row): array
    {
        $answer = (string) $row->get('jawaban', '');
        $letters = ['a', 'b', 'c', 'd', 'e'];

        $data = [
            'type' => $type,
            'body' => (string) $row->get('soal'),
            'reading' => $row->get('bacaan'),
            'points' => is_numeric($row->get('poin')) ? (float) $row->get('poin') : 1,
            'explanation' => $row->get('pembahasan'),
        ];

        return $data + match ($type) {
            QuestionType::MultipleChoice, QuestionType::Listening => [
                'options' => collect($letters)
                    ->filter(fn ($l) => filled($row->get('opsi_'.$l)))
                    ->map(fn ($l) => ['body' => (string) $row->get('opsi_'.$l), 'is_correct' => strtolower(trim($answer)) === $l])
                    ->values()->all(),
            ],
            QuestionType::TrueFalse => ['correct_bool' => in_array(strtolower(trim($answer)), ['benar', 'b', 'true', '1', 'ya'], true)],
            QuestionType::Tone => ['correct_tone' => (int) $answer],
            QuestionType::ShortAnswer => ['accepted_answers' => explode('|', $answer)],
            QuestionType::Matching => ['options' => collect(explode('|', $answer))
                ->map(fn ($pair) => array_map('trim', explode('=', $pair, 2)))
                ->filter(fn ($pair) => count($pair) === 2)
                ->map(fn ($pair) => ['body' => $pair[0], 'match_key' => $pair[1]])
                ->values()->all()],
            default => [],
        };
    }
}
