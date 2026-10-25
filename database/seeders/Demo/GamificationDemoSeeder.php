<?php

namespace Database\Seeders\Demo;

use App\Models\PointTransaction;
use Database\Seeders\GamificationSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Data contoh Fase 10: aturan, level, lencana, lalu poin/streak/lencana dihitung dari aktivitas demo yang sudah ada.
 */
class GamificationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GamificationSeeder::class);

        if (! PointTransaction::query()->exists()) {
            Artisan::call('app:gamification-backfill');
        }
    }
}
