<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\Batch;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\LeaderboardService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:weekly-champions')]
#[Description('Beri lencana "Bintang Pekan Ini" ke peserta terbaik tiap kelas untuk minggu lalu (Senin 06.00).')]
class WeeklyChampions extends Command
{
    public function handle(LeaderboardService $leaderboard, BadgeService $badges): int
    {
        $batch = Batch::current();
        $badge = Badge::query()->where('slug', 'bintang-pekan')->where('is_active', true)->first();

        if (! $batch || ! $badge) {
            return self::SUCCESS;
        }

        $granted = 0;
        foreach ($batch->classrooms as $classroom) {
            $best = $leaderboard->bestOfWeek(now()->subWeek(), $classroom);
            if ($best && $badges->grant($best['user'], $badge)) {
                $granted++;
            }
        }

        $this->info("{$granted} lencana Bintang Pekan Ini diberikan.");

        return self::SUCCESS;
    }
}
