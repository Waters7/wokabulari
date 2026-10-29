<?php

namespace Database\Factories;

use App\Enums\EvaluationCategory;
use App\Enums\PublishStatus;
use App\Models\Batch;
use App\Models\EvaluationQuestion;
use App\Models\ProgramEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramEvaluation>
 */
class ProgramEvaluationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Survei sarana & prasarana '.fake()->monthName(),
            'description' => 'Bantu kami memperbaiki fasilitas program.',
            'category' => EvaluationCategory::Facility,
            'target_participants' => true,
            'target_teachers' => false,
            'is_anonymous' => true,
            'opens_at' => null,
            'closes_at' => now()->addWeek(),
            'status' => PublishStatus::Draft,
        ];
    }

    /** Terbit dan sedang berjalan. */
    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published, 'published_at' => now()]);
    }

    public function closed(): static
    {
        return $this->published()->state(fn () => ['closes_at' => now()->subHour()]);
    }

    /** Tujukan ke angkatan (bawaan: angkatan baru). */
    public function forBatch(?Batch $batch = null): static
    {
        return $this->afterCreating(fn (ProgramEvaluation $evaluation) => $evaluation->batches()->attach(($batch ?? Batch::factory()->create())->id));
    }

    /** Tambahkan pertanyaan skala, pilihan, dan isian. */
    public function withQuestions(): static
    {
        return $this->afterCreating(function (ProgramEvaluation $evaluation) {
            EvaluationQuestion::factory()->for($evaluation, 'evaluation')->create(['aspect' => 'Ruang kelas', 'sort_order' => 1]);
            EvaluationQuestion::factory()->choice()->for($evaluation, 'evaluation')->create(['aspect' => 'Internet', 'sort_order' => 2]);
            EvaluationQuestion::factory()->text()->for($evaluation, 'evaluation')->create(['aspect' => 'Saran', 'sort_order' => 3, 'is_required' => false]);
        });
    }
}
