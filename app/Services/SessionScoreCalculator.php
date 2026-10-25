<?php

namespace App\Services;

use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use Illuminate\Support\Collection;

/**
 * Nilai akhir sesi = rata-rata tertimbang skor komponen yang dinormalisasi ke 0–100.
 * Komponen yang belum diberi skor untuk peserta tersebut tidak ikut dihitung.
 */
class SessionScoreCalculator
{
    /**
     * @param  Collection<int, SessionAssessmentComponent>  $components  dengan relasi scores dimuat
     * @return array<int, float|null> user_id => nilai akhir
     */
    public function forSession(LearningSession $session, ?Collection $components = null): array
    {
        $components ??= $session->components()->with('scores')->get();
        $userIds = $components->flatMap(fn ($c) => $c->scores->pluck('user_id'))->unique();

        return $userIds->mapWithKeys(fn ($userId) => [$userId => $this->forUser($components, $userId)])->all();
    }

    /**
     * @param  Collection<int, SessionAssessmentComponent>  $components
     */
    public function forUser(Collection $components, int $userId): ?float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($components as $component) {
            $score = $component->scores->firstWhere('user_id', $userId)?->score;

            if ($score === null || $component->max_score <= 0 || $component->weight <= 0) {
                continue;
            }

            $weighted += ($score / $component->max_score * 100) * $component->weight;
            $totalWeight += $component->weight;
        }

        return $totalWeight > 0 ? round($weighted / $totalWeight, 2) : null;
    }

    /** Normalisasi skor satu komponen ke 0–100. */
    public static function normalize(float $score, float $max): float
    {
        return $max > 0 ? round(min(100, max(0, $score / $max * 100)), 2) : 0.0;
    }
}
