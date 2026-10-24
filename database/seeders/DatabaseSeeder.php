<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Lokal: data dasar + data contoh (SPEC 8). Produksi: hanya data dasar.
     */
    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
