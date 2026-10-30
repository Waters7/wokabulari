<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Models\LearningSession;
use App\Services\SessionDocumentationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Sesi kelas yang sedang berjalan (data langsung, tanpa cache) untuk dashboard eksekutif.
 */
class RunningSessions
{
    public function __construct(protected SessionDocumentationRule $documentation) {}

    /**
     * @return array{sessions: list<array<string, mixed>>, today: array<string, int>}
     */
    public function overview(?int $batchId = null, ?int $classroomId = null): array
    {
        $scope = fn (Builder $q) => $q->whereHas('classroom', fn ($q) => $q
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when($classroomId, fn ($q) => $q->whereKey($classroomId)));

        $sessions = LearningSession::query()->tap($scope)
            ->where('status', SessionStatus::Ongoing)
            ->with(['classroom' => fn ($q) => $q->withCount('participants')->with(['language', 'teacher']), 'teacher', 'activeCode',
                'documentations' => fn ($q) => $q->where('phase', SessionPhase::Start)])
            ->withCount(['attendances as attended_count' => fn ($q) => $q->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])])
            ->orderBy('started_at')
            ->get()
            ->map(function (LearningSession $session) {
                $end = Carbon::parse($session->date->toDateString().' '.$session->end_time);
                $photo = $session->documentations->first(fn ($d) => $d->isImage());

                return [
                    'session' => $session,
                    'teacher' => $session->teacher?->name ?? $session->classroom->teacher?->name,
                    'minutes' => $session->started_at ? (int) $session->started_at->diffInMinutes(now()) : null,
                    'overtime' => now()->greaterThan($end) ? (int) $end->diffInMinutes(now()) : 0,
                    'attended' => (int) $session->attended_count,
                    'participants' => (int) $session->classroom->participants_count,
                    'codeActive' => $session->activeCode !== null,
                    'photo' => $photo?->url(),
                    'missingDoc' => $session->documentations->isEmpty() && $this->documentation->required($session),
                ];
            })->all();

        $today = LearningSession::query()->tap($scope)->whereDate('date', today())
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'sessions' => $sessions,
            'today' => collect([SessionStatus::Scheduled, SessionStatus::Ongoing, SessionStatus::Completed])
                ->mapWithKeys(fn (SessionStatus $s) => [$s->value => (int) ($today[$s->value] ?? 0)])->all(),
        ];
    }
}
