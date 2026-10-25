<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\PointService;
use App\Services\GuardianOverview;
use Illuminate\Support\ServiceProvider;

/**
 * Menyambungkan gamifikasi ke portal wali (poin, level, lencana anak).
 */
class GamificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        GuardianOverview::extend('gamification', function (User $child): array {
            $total = app(PointService::class)->total($child);

            return [
                'view' => 'gamification.guardian-card',
                'name' => strtok($child->name, ' '),
                'progress' => app(LevelService::class)->progress($total),
                'streak' => $child->streak,
                'badges' => $child->badges()->orderByPivot('earned_at', 'desc')->get(),
            ];
        });
    }
}
