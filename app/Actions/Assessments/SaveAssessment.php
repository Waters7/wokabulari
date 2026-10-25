<?php

namespace App\Actions\Assessments;

use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use App\Events\AssessmentPublished;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\User;
use App\Services\GradeService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Simpan (draf) dan terbitkan penilaian mingguan/bulanan. Rata-rata & predikat dihitung otomatis.
 */
class SaveAssessment
{
    public function __construct(protected GradeService $grades) {}

    /**
     * @param  array<string, mixed>  $values  aspek => nilai (0–100), feedback, visible_to_guardian
     */
    public function save(Classroom $classroom, User $participant, User $teacher, PeriodType $type, CarbonInterface $date, array $values): Assessment
    {
        if (! $classroom->participants()->whereKey($participant->id)->exists()) {
            throw new InvalidArgumentException('Peserta bukan anggota kelas ini.');
        }

        [$start, $end] = $type->range($date);

        $scores = [];
        foreach (LanguageAspect::periodic() as $aspect) {
            $raw = $values[$aspect->value] ?? null;
            $raw = ($raw === '' || $raw === null) ? null : (float) str_replace(',', '.', (string) $raw);

            if ($raw !== null && ($raw < 0 || $raw > 100)) {
                throw new InvalidArgumentException('Nilai '.$aspect->label().' harus 0–100.');
            }

            $scores[$aspect->value] = $raw === null ? null : round($raw, 2);
        }

        $average = GradeService::average($scores);

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

        $assessment->fill($scores + [
            'period_end' => $end,
            'teacher_id' => $teacher->id,
            'average' => $average,
            'grade' => $this->grades->grade($average),
            'feedback' => $values['feedback'] ?? $assessment->feedback,
            'visible_to_guardian' => (bool) ($values['visible_to_guardian'] ?? $assessment->visible_to_guardian ?? true),
        ])->save();

        return $assessment;
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
