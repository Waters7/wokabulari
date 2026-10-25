<?php

namespace App\Services;

use App\Enums\LanguageAspect;
use App\Models\Assessment;
use App\Models\LearningSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Data halaman nilai peserta (dipakai juga portal wali & profil 360°).
 */
class GradeReport
{
    /** Bagian tambahan dari fase berikutnya (quiz, tugas, pretest–posttest). */
    protected static array $sections = [];

    /**
     * @param  callable(User): (array<string, mixed>|null)  $section
     */
    public static function section(string $key, callable $section): void
    {
        static::$sections[$key] = $section;
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, bool $guardianView = false): array
    {
        $assessments = Assessment::query()->published()->where('user_id', $user->id)
            ->when($guardianView, fn ($q) => $q->where('visible_to_guardian', true))
            ->orderBy('period_start')->get();

        $notes = SessionNote::query()->where('user_id', $user->id)
            ->when($guardianView, fn ($q) => $q->where('visible_to_guardian', true))
            ->with(['session', 'teacher'])->latest()->get();

        return [
            'assessments' => $assessments,
            'notes' => $notes,
            'latest' => $assessments->last(),
            'lineChart' => $this->lineChart($assessments),
            'radarChart' => $this->radarChart($assessments->last()),
            'sessionScores' => $this->sessionScores($user),
            'timeline' => $this->timeline($assessments, $notes),
            'sections' => collect(static::$sections)->map(fn ($section) => $section($user))->filter()->all(),
        ];
    }

    protected function lineChart(Collection $assessments): array
    {
        $weekly = $assessments->where('period_type.value', 'weekly');
        $source = $weekly->isNotEmpty() ? $weekly : $assessments;

        return [
            'type' => 'line',
            'max' => 100,
            'data' => [
                'labels' => $source->map(fn (Assessment $a) => tanggal($a->period_start, 'j M'))->values(),
                'datasets' => collect(LanguageAspect::periodic())->map(fn (LanguageAspect $aspect) => [
                    'label' => $aspect->label(),
                    'data' => $source->map(fn (Assessment $a) => $a->{$aspect->value})->values(),
                ])->values(),
            ],
        ];
    }

    protected function radarChart(?Assessment $latest): ?array
    {
        if (! $latest) {
            return null;
        }

        return [
            'type' => 'radar',
            'data' => [
                'labels' => collect(LanguageAspect::periodic())->map->label(),
                'datasets' => [[
                    'label' => $latest->periodLabel(),
                    'data' => collect(LanguageAspect::periodic())->map(fn ($a) => $latest->{$a->value})->values(),
                ]],
            ],
        ];
    }

    /**
     * Nilai akhir per sesi (sesi yang punya skor untuk peserta).
     *
     * @return Collection<int, array{session: LearningSession, score: float|null}>
     */
    protected function sessionScores(User $user): Collection
    {
        $calculator = app(SessionScoreCalculator::class);

        return LearningSession::query()
            ->whereHas('components.scores', fn ($q) => $q->where('user_id', $user->id))
            ->with(['components.scores' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('date')
            ->limit(20)
            ->get()
            ->map(fn (LearningSession $s) => ['session' => $s, 'score' => $calculator->forUser($s->components, $user->id)]);
    }

    protected function timeline(Collection $assessments, Collection $notes): Collection
    {
        return $assessments->map(fn (Assessment $a) => ['type' => 'assessment', 'date' => $a->published_at ?? $a->period_end, 'item' => $a, 'unread' => $a->read_at === null])
            ->concat($notes->map(fn (SessionNote $n) => ['type' => 'note', 'date' => $n->created_at, 'item' => $n, 'unread' => $n->read_at === null]))
            ->sortByDesc(fn ($e) => $e['date']?->timestamp ?? 0)
            ->values();
    }
}
