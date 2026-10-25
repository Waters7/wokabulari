<?php

namespace Database\Seeders\Demo;

use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Models\GuardianInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh Fase 9: wali01…wali32@wokabulari.test (password "password") terhubung ke peserta01…peserta32.
 */
class GuardianDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'wali01@wokabulari.test')->exists()) {
            return;
        }

        $password = Hash::make('password');
        $admin = User::query()->where('email', 'program@wokabulari.test')->first();
        $participants = User::query()->where('username', 'like', 'peserta%')->orderBy('username')->get();

        foreach ($participants as $i => $participant) {
            $number = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $mother = $i % 3 !== 0;
            $lastName = trim(strrchr($participant->name, ' ') ?: '');

            $guardian = User::query()->create([
                'name' => ($mother ? 'Ibu ' : 'Bapak ').($mother ? fake('id_ID')->firstNameFemale() : fake('id_ID')->firstNameMale()).' '.$lastName,
                'email' => "wali{$number}@wokabulari.test",
                'phone' => '6281399'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'password' => $password,
                'is_active' => true,
                'must_change_password' => false,
                'consented_at' => now(),
                'consent_version' => config('wokabulari.consent_version'),
            ]);
            $guardian->assignRole(Role::Guardian->value);

            // Satu hubungan dibiarkan menunggu verifikasi untuk uji halaman admin.
            $pending = $i === count($participants) - 1;
            DB::table('guardian_participant')->insert([
                'guardian_id' => $guardian->id,
                'participant_id' => $participant->id,
                'relationship' => ($mother ? GuardianRelationship::Mother : GuardianRelationship::Father)->value,
                'is_primary' => true,
                'verified_at' => $pending ? null : now()->subDays(10),
                'verified_by' => $pending ? null : $admin?->id,
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(10),
            ]);
        }

        if ($first = $participants->first()) {
            GuardianInvitation::query()->create([
                'participant_id' => $first->id,
                'phone' => '6281399099999',
                'relationship' => GuardianRelationship::Guardian,
                'code' => 'WALIDEMO',
                'expires_at' => now()->addDays(14),
                'created_by' => $admin?->id,
            ]);
        }
    }
}
