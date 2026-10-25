<?php

namespace App\Console\Commands;

use App\Services\Gamification\StreakService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:update-streaks')]
#[Description('Putus streak peserta yang melewatkan hari kelas kemarin (00.05).')]
class UpdateStreaks extends Command
{
    public function handle(StreakService $streaks): int
    {
        $this->info($streaks->resetMissed().' streak diputus.');

        return self::SUCCESS;
    }
}
