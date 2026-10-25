<?php

namespace App\Providers;

use App\Services\DormRoster;
use App\Services\MealRoster;
use Illuminate\Support\ServiceProvider;

/**
 * Menyambungkan modul asrama & kesehatan ke modul lain (penanda porsi konsumsi).
 */
class DormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Peserta yang izin keluar atau sakit ditandai di monitor makan agar porsi bisa disesuaikan.
        MealRoster::addFlagSource('dorm', [DormRoster::class, 'mealFlags']);
    }
}
