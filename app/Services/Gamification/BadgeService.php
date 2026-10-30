<?php

namespace App\Services\Gamification;

use App\Enums\PointEvent;
use App\Models\Assessment;
use App\Models\AssignmentSubmission;
use App\Models\Badge;
use App\Models\User;
use App\Notifications\BadgeEarnedNotification;
use Illuminate\Support\Collection;

/**
 * Lencana: kriteria JSON dievaluasi setiap poin/streak berubah.
 * Tipe kriteria: points{min}, streak{days}, event_count{event,count,no_late_assignments?}, aspect{aspect,min}, weekly_top (diberikan job mingguan).
 */
class BadgeService
{
    public function __construct(protected PointService $points) {}

    /**
     * @return Collection<int, Badge> lencana yang baru diperoleh
     */
    public function evaluate(User $user): Collection
    {
        if (! $user->isParticipant()) {
            return collect();
        }

        $owned = $user->badges()->pluck('badges.id');

        return Badge::query()->where('is_active', true)->whereNotIn('id', $owned)->get()
            ->filter(fn (Badge $badge) => $this->qualifies($user, $badge->criteria ?? []))
            ->each(function (Badge $badge) use ($user) {
                $this->grant($user, $badge);
            })
            ->values();
    }

    public function grant(User $user, Badge $badge): bool
    {
        if ($user->badges()->whereKey($badge->id)->exists()) {
            return false;
        }

        $user->badges()->attach($badge->id, ['earned_at' => now()]);
        $user->notify(new BadgeEarnedNotification($badge));

        return true;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function qualifies(User $user, array $criteria): bool
    {
        return match ($criteria['type'] ?? null) {
            'points' => $this->points->total($user) >= (int) $criteria['min'],
            'streak' => (int) ($user->streak()->value('longest_streak') ?? 0) >= (int) $criteria['days'],
            'event_count' => $this->eventCount($user, $criteria),
            'aspect' => (float) (Assessment::query()->published()->where('user_id', $user->id)->latest('period_end')->first()?->scoreFor((string) $criteria['aspect']) ?? 0) >= (float) $criteria['min'],
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function eventCount(User $user, array $criteria): bool
    {
        $event = PointEvent::tryFrom((string) ($criteria['event'] ?? ''));
        if (! $event || $this->points->count($user, $event) < (int) $criteria['count']) {
            return false;
        }

        if (! empty($criteria['no_late_assignments'])) {
            return ! AssignmentSubmission::query()->where('user_id', $user->id)->where('is_late', true)->exists();
        }

        return true;
    }
}
