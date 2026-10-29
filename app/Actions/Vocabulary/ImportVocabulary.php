<?php

namespace App\Actions\Vocabulary;

use App\Http\Requests\VocabularyWordRequest;
use App\Models\Language;
use App\Models\User;
use App\Models\VocabularyWord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Import Bank Kosakata dari Excel/CSV (kolom templat: kata, cara_baca, arti, topik, contoh, arti_contoh, urutan, aktif).
 * Kata yang sudah ada di bahasa yang sama diperbarui; baris bermasalah dilewati dan dilaporkan.
 */
class ImportVocabulary
{
    public const MAX_ROWS = 2000;

    /** Nama kolom lain yang juga diterima. */
    protected const ALIASES = [
        'word' => 'kata', 'bacaan' => 'cara_baca', 'pinyin' => 'cara_baca', 'romaji' => 'cara_baca', 'reading' => 'cara_baca',
        'meaning' => 'arti', 'topic' => 'topik', 'example' => 'contoh', 'example_meaning' => 'arti_contoh', 'sort_order' => 'urutan', 'active' => 'aktif',
    ];

    /**
     * @param  iterable<array<string, mixed>|Collection<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function handle(Language $language, iterable $rows, User $by): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;
        $nextOrder = (int) VocabularyWord::query()->where('language_id', $language->id)->max('sort_order');

        DB::transaction(function () use ($language, $rows, $by, &$created, &$updated, &$errors, &$line, &$nextOrder) {
            foreach ($rows as $row) {
                $line++;
                $row = $this->normalize($row);

                if ($row->filter(fn ($value) => filled($value))->isEmpty()) {
                    continue;
                }

                if ($line - 1 > self::MAX_ROWS) {
                    $errors[] = 'Baris setelah '.self::MAX_ROWS.' dilewati; bagi berkas menjadi beberapa bagian.';
                    break;
                }

                $data = [
                    'language_id' => $language->id,
                    'word' => $this->text($row->get('kata')),
                    'reading' => $this->text($row->get('cara_baca')),
                    'meaning' => $this->text($row->get('arti')),
                    'topic' => $this->text($row->get('topik')),
                    'example' => $this->text($row->get('contoh')),
                    'example_meaning' => $this->text($row->get('arti_contoh')),
                    'sort_order' => is_numeric($row->get('urutan')) ? (int) $row->get('urutan') : null,
                    'is_active' => ! in_array(Str::lower((string) $row->get('aktif')), ['tidak', 'no', 'n', '0', 'false', 'nonaktif'], true),
                ];

                $validator = Validator::make($data, VocabularyWordRequest::rulesFor(), [], VocabularyWordRequest::attributesFor());
                if ($validator->fails()) {
                    $errors[] = "Baris {$line}: ".implode(' ', $validator->errors()->all());

                    continue;
                }

                $existing = VocabularyWord::query()->where('language_id', $language->id)->where('word', $data['word'])->first();

                if ($existing) {
                    $existing->update(array_filter($data, fn ($value, $key) => $key !== 'sort_order' || $value !== null, ARRAY_FILTER_USE_BOTH));
                    $updated++;
                } else {
                    VocabularyWord::query()->create(['sort_order' => $data['sort_order'] ?? ++$nextOrder, 'created_by' => $by->id] + $data);
                    $nextOrder = max($nextOrder, (int) ($data['sort_order'] ?? 0));
                    $created++;
                }
            }
        });

        activity('kosakata')->causedBy($by)->performedOn($language)
            ->withProperties(['baru' => $created, 'diperbarui' => $updated, 'galat' => count($errors)])
            ->log('Import bank kosakata');

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /** @return Collection<string, mixed> */
    protected function normalize(mixed $row): Collection
    {
        return collect($row instanceof Collection ? $row->all() : (array) $row)
            ->mapWithKeys(function ($value, $key) {
                $key = Str::slug((string) $key, '_');

                return [self::ALIASES[$key] ?? $key => is_string($value) ? trim($value) : $value];
            });
    }

    protected function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
