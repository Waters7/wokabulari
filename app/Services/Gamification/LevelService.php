<?php

namespace App\Services\Gamification;

use App\Models\Level;
use Illuminate\Support\Collection;

/**
 * Level bertema bumbu woku berdasarkan total poin.
 */
class LevelService
{
    /** @var Collection<int, Level>|null */
    protected ?Collection $levels = null;

    /** @return Collection<int, Level> */
    public function all(): Collection
    {
        return $this->levels ??= Level::query()->orderBy('min_points')->get();
    }

    public function forPoints(int $points): ?Level
    {
        return $this->all()->filter(fn (Level $l) => $l->min_points <= $points)->last();
    }

    public function next(int $points): ?Level
    {
        return $this->all()->first(fn (Level $l) => $l->min_points > $points);
    }

    /**
     * @return array{level: ?Level, next: ?Level, points: int, progress: int, remaining: int}
     */
    public function progress(int $points): array
    {
        $level = $this->forPoints($points);
        $next = $this->next($points);
        $base = $level?->min_points ?? 0;
        $span = $next ? max($next->min_points - $base, 1) : 1;

        return [
            'level' => $level,
            'next' => $next,
            'points' => $points,
            'progress' => $next ? (int) min(100, max(0, round(($points - $base) / $span * 100))) : 100,
            'remaining' => $next ? $next->min_points - $points : 0,
        ];
    }
}
