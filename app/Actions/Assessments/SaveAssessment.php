<?php

namespace App\Actions\Assessments;

use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use App\Events\AssessmentPublished;
use App\Models\Assessment;
use App\Models\AssessmentAspect;
use App\Models\Classroom;
use App\Models\User;
use App\Services\GradeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Simpan (draf) dan terbitkan penilaian mingguan/bulanan. Rata-rata & predikat dihitung otomatis
 * dari semua nilai aspek pada penilaian tersebut.
 */
class SaveAssessment
{
    public function __construct(protected GradeService $grades) {}

    /**
     * @param  array<string, mixed>  $values  scores (id aspek => nilai 0–100), feedback, visible_to_guardian
     */
    public function save(Classroom $classroom, User $participant, User $teacher, PeriodType $type, CarbonInterface $date, array $values): Assessment
    {
        if (! $classroom->participants()->whereKey($participant->id)->exists()) {
            throw new InvalidArgumentException('Peserta bukan anggota kelas ini.');
        }

        [$start, $end] = $type->range($date);

        $assessment = Assessment::query()
            ->where('classroom_id', $classroom->id)
            ->where('user_id', $participant->id)
            ->where('period_type', $type)
            ->whereDate('period_start', $start)
            ->first() ?? new Assessment([
                'classroom_id' => $classroom->id,
                'user_id' => $participant->id,
                'period_type' => $type,
                'period_start' => $start,
                'status' => PublishStatus::Draft,
            ]);

        $scores = $this->scores($classroom, $assessment, $values['scores'] ?? []);

        return DB::transaction(function () use ($assessment, $scores, $end, $teacher, $values) {
            $assessment->fill([
                'period_end' => $end,
                'teacher_id' => $teacher->id,
                'feedback' => $values['feedback'] ?? $assessment->feedback,
                'visible_to_guardian' => (bool) ($values['visible_to_guardian'] ?? $assessment->visible_to_guardian ?? true),
            ])->save();

            foreach ($scores as $aspectId => $score) {
                $score === null
                    ? $assessment->scores()->where('assessment_aspect_id', $aspectId)->delete()
                    : $assessment->scores()->updateOrCreate(['assessment_aspect_id' => $aspectId], ['score' => $score]);
            }

            $average = GradeService::average($assessment->scores()->pluck('score')->map(fn ($v) => (float) $v));
            $assessment->update(['average' => $average, 'grade' => $this->grades->grade($average)]);

            return $assessment->unsetRelation('scores');
        });
    }

    /**
     * Validasi nilai: aspek harus aktif untuk bahasa kelas (atau sudah berisi nilai), nilai 0–100.
     *
     * @param  array<int|string, mixed>  $input
     * @return array<int, float|null>
     */
    protected function scores(Classroom $classroom, Assessment $assessment, array $input): array
    {
        $aspects = AssessmentAspect::query()->whereIn('id', array_keys($input))->get()->keyBy('id');
        $existing = $assessment->exists ? $assessment->scores()->pluck('assessment_aspect_id')->all() : [];

        $scores = [];
        foreach ($input as $aspectId => $raw) {
            $aspect = $aspects->get((int) $aspectId);
            $allowed = $aspect && (in_array($aspect->id, $existing, true)
                || ($aspect->is_active && ($aspect->language_id === null || (int) $aspect->language_id === (int) $classroom->language_id)));

            if (! $allowed) {
                throw new InvalidArgumentException('Aspek penilaian tidak tersedia untuk kelas ini.');
            }

            $raw = ($raw === '' || $raw === null) ? null : str_replace(',', '.', trim((string) $raw));
            if ($raw !== null && ! is_numeric($raw)) {
                throw new InvalidArgumentException('Nilai '.$aspect->name.' harus berupa angka.');
            }
            if ($raw !== null && ((float) $raw < 0 || (float) $raw > 100)) {
                throw new InvalidArgumentException('Nilai '.$aspect->name.' harus 0–100.');
            }

            $scores[$aspect->id] = $raw === null ? null : round((float) $raw, 2);
        }

        return $scores;
    }

    /**
     * @param  Collection<int, Assessment>|list<Assessment>  $assessments
     */
    public function publish(iterable $assessments): int
    {
        $count = 0;

        foreach ($assessments as $assessment) {
            if ($assessment->isPublished() || $assessment->average === null) {
                continue;
            }

            $assessment->update(['status' => PublishStatus::Published, 'published_at' => now(), 'read_at' => null]);
            event(new AssessmentPublished($assessment));
            $count++;
        }

        return $count;
    }

    public function unpublish(Assessment $assessment): void
    {
        $assessment->update(['status' => PublishStatus::Draft, 'published_at' => null]);
    }
}
