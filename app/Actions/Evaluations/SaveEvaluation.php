<?php

namespace App\Actions\Evaluations;

use App\Enums\EvaluationQuestionType;
use App\Enums\PublishStatus;
use App\Models\ProgramEvaluation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Simpan survei beserta sasaran dan pertanyaannya.
 * Setelah ada jawaban, hanya judul, keterangan, dan tenggat yang boleh diubah agar hasil tetap sahih.
 */
class SaveEvaluation
{
    /**
     * @param  array<string, mixed>  $data  tervalidasi ProgramEvaluationRequest
     */
    public function handle(array $data, User $actor, ?ProgramEvaluation $evaluation = null): ProgramEvaluation
    {
        $opensAt = filled($data['opens_at'] ?? null) ? CarbonImmutable::parse($data['opens_at']) : null;
        $closesAt = CarbonImmutable::parse($data['closes_at']);

        if ($opensAt && $closesAt->lte($opensAt)) {
            throw new InvalidArgumentException('Tenggat harus setelah waktu survei dibuka.');
        }

        if (! ($data['target_participants'] ?? false) && ! ($data['target_teachers'] ?? false)) {
            throw new InvalidArgumentException('Pilih responden: peserta, pengajar, atau keduanya.');
        }

        $questions = $this->questions($data['questions'] ?? []);
        $locked = $evaluation?->responses()->exists() ?? false;

        return DB::transaction(function () use ($data, $actor, $evaluation, $opensAt, $closesAt, $questions, $locked) {
            $evaluation ??= new ProgramEvaluation(['status' => PublishStatus::Draft, 'created_by' => $actor->id]);

            $evaluation->fill(['title' => trim($data['title']), 'description' => $data['description'] ?? null, 'closes_at' => $closesAt]);

            if (! $locked) {
                $evaluation->fill([
                    'category' => $data['category'],
                    'target_participants' => (bool) ($data['target_participants'] ?? false),
                    'target_teachers' => (bool) ($data['target_teachers'] ?? false),
                    'is_anonymous' => (bool) ($data['is_anonymous'] ?? true),
                    'opens_at' => $opensAt,
                ]);
            }

            // Tenggat diperpanjang: pengingat boleh dikirim lagi.
            if ($evaluation->exists && $evaluation->isDirty('closes_at')) {
                $evaluation->reminded_at = null;
            }

            $evaluation->save();

            if (! $locked) {
                $evaluation->batches()->sync($data['batch_ids']);
                $evaluation->questions()->delete();
                foreach ($questions as $i => $question) {
                    $evaluation->questions()->create($question + ['sort_order' => $i + 1]);
                }
            }

            return $evaluation->load(['batches', 'questions']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function questions(array $rows): array
    {
        return collect($rows)->values()->map(function (array $row, int $i) {
            $type = EvaluationQuestionType::from($row['type']);
            $options = null;

            if ($type === EvaluationQuestionType::Choice) {
                $options = collect(preg_split('/\r\n|\r|\n/', (string) ($row['options'] ?? '')))
                    ->map(fn ($option) => trim($option))->filter()->unique()->values()->all();

                if (count($options) < 2) {
                    throw new InvalidArgumentException('Pertanyaan pilihan ganda nomor '.($i + 1).' butuh minimal 2 pilihan (satu per baris).');
                }
            }

            return [
                'aspect' => filled($row['aspect'] ?? null) ? trim($row['aspect']) : null,
                'type' => $type,
                'body' => trim($row['body']),
                'options' => $options,
                'audience' => $row['audience'],
                'is_required' => (bool) ($row['is_required'] ?? true),
            ];
        })->all();
    }
}
