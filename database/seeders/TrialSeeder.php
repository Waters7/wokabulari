<?php

namespace Database\Seeders;

use App\Actions\Participants\ImportParticipants;
use App\Enums\Role;
use App\Imports\ParticipantRowsImport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Data uji coba (trial) di localhost/staging:
 * data dasar + data contoh semua fase + peserta asli dari database/data/*.csv (bila ada)
 * + super admin dari TRIAL_ADMIN_USERNAME / TRIAL_ADMIN_PASSWORD di .env.
 *
 * Jalankan: php artisan migrate:fresh --seed --seeder=TrialSeeder
 */
class TrialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionSeeder::class);
        $this->call(DemoSeeder::class);

        $this->importRealParticipants();
        $this->createTrialAdmin();
    }

    /** Peserta asli disimpan di database/data/*.csv yang tidak ikut ter-commit (data pribadi). */
    protected function importRealParticipants(): void
    {
        $files = collect(glob(database_path('data/*.csv')) ?: [])
            ->reject(fn ($file) => str_starts_with(basename($file), 'contoh-'));

        if ($files->isEmpty()) {
            $this->command?->warn('Tidak ada berkas peserta asli di database/data — dilewati.');

            return;
        }

        if (blank(config('wokabulari.participant_default_password'))) {
            $this->command?->warn('PARTICIPANT_DEFAULT_PASSWORD belum diisi — import peserta asli dilewati.');

            return;
        }

        foreach ($files as $file) {
            $reader = new ParticipantRowsImport;
            Excel::import($reader, $file, null, \Maatwebsite\Excel\Excel::CSV);
            $result = app(ImportParticipants::class)->handle($reader->rows);

            $this->command?->info(basename($file).': '.$result->summary());
            foreach ($result->warnings as $warning) {
                $this->command?->warn('  '.$warning);
            }
        }
    }

    /** Super admin trial; kata sandi hanya dibaca dari .env, tidak pernah ditulis di repo. */
    public function createTrialAdmin(): void
    {
        $username = config('wokabulari.trial_admin.username');
        $password = config('wokabulari.trial_admin.password');

        if (blank($username) || blank($password)) {
            $this->command?->warn('TRIAL_ADMIN_USERNAME/TRIAL_ADMIN_PASSWORD belum diisi — super admin trial tidak dibuat.');

            return;
        }

        $admin = User::query()->withTrashed()->firstOrNew(['username' => $username]);
        $admin->fill([
            'name' => 'Super Admin',
            'password' => $password,
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => now(),
        ])->save();

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->syncRoles([Role::SuperAdmin->value]);

        $this->command?->info("Super admin trial siap: login dengan username \"{$username}\".");
    }
}
