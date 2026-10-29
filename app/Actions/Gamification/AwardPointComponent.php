<?php

namespace App\Actions\Gamification;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\GamificationRule;
use App\Models\PointAward;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Gamification\PointService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pengajar/admin memberi komponen poin tambahan ke peserta satu kelas, opsional terkait tugas atau quiz kelas itu.
 */
class AwardPointComponent
{
    public function __construct(protected PointService $points) {}

    /**
     * @param  iterable<int|string>  $participantIds
     * @return array{award: PointAward, awarded: int}
     */
    public function handle(GamificationRule $component, Classroom $classroom, iterable $participantIds, User $by, ?string $note = null, ?int $assignmentId = null, ?int $quizId = null): array
    {
        if (! $component->isAwardable()) {
            throw new InvalidArgumentException('Komponen ini belum disetujui atau sedang nonaktif.');
        }

        if (! $by->can('gamification.manage') && ! $classroom->hasTeacher($by)) {
            throw new InvalidArgumentException('Poin hanya bisa diberikan ke peserta kelas yang Anda ajar.');
        }

        $ids = collect($participantIds)->map(fn ($id) => (int) $id)->all();
        $participants = $classroom->participants()->where('users.is_active', true)->whereIn('users.id', $ids)->get();
        if ($participants->isEmpty()) {
            throw new InvalidArgumentException('Pilih minimal satu peserta kelas ini.');
        }

        if ($assignmentId && ! Assignment::query()->where('classroom_id', $classroom->id)->whereKey($assignmentId)->exists()) {
            throw new InvalidArgumentException('Tugas tidak ditemukan di kelas ini.');
        }

        if ($quizId && ! Quiz::query()->where('classroom_id', $classroom->id)->whereKey($quizId)->exists()) {
            throw new InvalidArgumentException('Quiz tidak ditemukan di kelas ini.');
        }

        return DB::transaction(function () use ($component, $classroom, $participants, $by, $note, $assignmentId, $quizId) {
            $award = PointAward::query()->create([
                'gamification_rule_id' => $component->id,
                'classroom_id' => $classroom->id,
                'assignment_id' => $assignmentId,
                'quiz_id' => $quizId,
                'points' => $component->points,
                'note' => filled($note) ? trim($note) : null,
                'awarded_by' => $by->id,
            ])->load(['component', 'assignment', 'quiz']);

            $awarded = $participants->filter(fn (User $participant) => $this->points->awardComponent($participant, $award, $by) !== null)->count();

            activity('gamifikasi')->causedBy($by)->performedOn($award)
                ->withProperties(['komponen' => $component->name, 'kelas' => $classroom->name, 'peserta' => $awarded])
                ->log('Memberi poin komponen tambahan');

            return ['award' => $award, 'awarded' => $awarded];
        });
    }
}
