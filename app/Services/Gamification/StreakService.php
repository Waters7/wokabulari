<?php

namespace App\Services\Gamification;

use App\Models\LearningSession;
use App\Models\Streak;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Streak harian: hari berturut-turut hadir & aktif. Hari tanpa sesi kelas (mis. Minggu) tidak memutus streak.
 */
class StreakService
{
    public function touch(User $user, ?CarbonInterface $date = null): Streak
    {
        $date = CarbonImmutable::parse($date ?? today())->startOfDay();
        $streak = Streak::query()->firstOrCreate(['user_id' => $user->id], ['current_streak' => 0, 'longest_streak' => 0]);
        $last = $streak->last_activity_date;

        if ($last && $last->isSameDay($date)) {
            return $streak;
        }

        if ($last && $last->gt($date)) {
            return $streak; // data lama (backfill) tidak memundurkan streak
        }

        $continues = $last && ! $this->hasMissedClassDay($user, CarbonImmutable::parse($last)->addDay(), $date->subDay());
        $current = $continues ? $streak->current_streak + 1 : 1;

        $streak->update([
            'current_streak' => $current,
            'longest_streak' => max($streak->longest_streak, $current),
            'last_activity_date' => $date,
        ]);

        return $streak;
    }

    /**
     * Putus streak peserta yang melewatkan hari kelas (dijalankan 00.05 untuk hari kemarin).
     */
    public function resetMissed(?CarbonInterface $day = null): int
    {
        $day = CarbonImmutable::parse($day ?? today()->subDay())->startOfDay();
        $reset = 0;

        Streak::query()->where('current_streak', '>', 0)->whereDate('last_activity_date', '<', $day)->with('user')->each(function (Streak $streak) use ($day, &$reset) {
            if ($streak->user && $this->hasMissedClassDay($streak->user, CarbonImmutable::parse($streak->last_activity_date)->addDay(), $day)) {
                $streak->update(['current_streak' => 0]);
                $reset++;
            }
        });

        return $reset;
    }

    /** Apakah ada hari kelas (sesi terjadwal untuk kelas peserta) di rentang tanggal tanpa aktivitas. */
    protected function hasMissedClassDay(User $user, CarbonInterface $from, CarbonInterface $to): bool
    {
        if ($from->gt($to)) {
            return false;
        }

        $classroomIds = $user->classrooms()->pluck('classrooms.id');

        return LearningSession::query()->whereIn('classroom_id', $classroomIds)
            ->whereDateBetween('date', $from->toDateString(), $to->toDateString())
            ->where('status', '!=', 'cancelled')
            ->exists();
    }
}
