<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\LocationType;
use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Dormitory;
use App\Models\Language;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Services\StaffUnitService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk uji di localhost (SPEC 8). Password semua akun: "password".
 * Dilengkapi bertahap tiap fase lewat seeder di namespace Database\Seeders\Demo.
 */
class DemoSeeder extends Seeder
{
    protected const PHASE_SEEDERS = [
        'LearningDemoSeeder', 'AssessmentDemoSeeder', 'AssignmentDemoSeeder', 'QuizDemoSeeder',
        'MealDemoSeeder', 'DormDemoSeeder', 'GuardianDemoSeeder', 'GamificationDemoSeeder', 'ProgramDemoSeeder',
    ];

    protected string $password;

    public function run(): void
    {
        $this->password = Hash::make('password');

        $batch = Batch::query()->updateOrCreate(['name' => 'Angkatan 1 2026'], [
            'start_date' => now()->startOfWeek()->subWeeks(2)->toDateString(),
            'end_date' => now()->startOfWeek()->addWeeks(10)->toDateString(),
            'attendance_target' => 80,
            'passing_score' => 70,
            'is_active' => true,
        ]);

        // Koordinat contoh kampus & asrama Poltekkes Kemenkes Manado (ubah di menu Lokasi).
        $campus = Location::query()->updateOrCreate(['name' => 'Kampus Poltekkes Kemenkes Manado'], ['type' => LocationType::Classroom, 'latitude' => 1.4605, 'longitude' => 124.8228, 'radius_m' => 200]);
        $dormFemaleLoc = Location::query()->updateOrCreate(['name' => 'Asrama Putri Poltekkes'], ['type' => LocationType::Dormitory, 'latitude' => 1.4612, 'longitude' => 124.8236, 'radius_m' => 100]);
        $dormMaleLoc = Location::query()->updateOrCreate(['name' => 'Asrama Putra Poltekkes'], ['type' => LocationType::Dormitory, 'latitude' => 1.4597, 'longitude' => 124.8241, 'radius_m' => 100]);

        $this->staffAccounts();

        $teachers = [];
        foreach (['en' => 'Grace Wuisan', 'zh' => 'Lina Tan', 'ja' => 'Yohana Sumampouw', 'de' => 'Markus Lumingkewas'] as $code => $name) {
            $teachers[$code] = $this->account("pengajar.{$code}@wokabulari.test", $name, Role::Teacher, ['username' => '19850101'.str_pad((string) (array_search($code, ['en', 'zh', 'ja', 'de']) + 1), 10, '0', STR_PAD_LEFT)]);
        }

        $dorms = [
            Gender::Female->value => Dormitory::query()->updateOrCreate(['name' => 'Asrama Putri Kemangi'], ['gender' => Gender::Female, 'location_id' => $dormFemaleLoc->id, 'capacity' => 24, 'curfew_time' => '22:00', 'check_window_start' => '20:00', 'check_window_end' => '21:30']),
            Gender::Male->value => Dormitory::query()->updateOrCreate(['name' => 'Asrama Putra Cakalang'], ['gender' => Gender::Male, 'location_id' => $dormMaleLoc->id, 'capacity' => 24, 'curfew_time' => '22:00', 'check_window_start' => '20:00', 'check_window_end' => '21:30']),
        ];
        foreach ($dorms as $dorm) {
            foreach (range(1, 4) as $i) {
                $dorm->rooms()->updateOrCreate(['number' => ($dorm->gender === Gender::Female ? 'A' : 'B').'10'.$i], ['floor' => '1', 'capacity' => 6]);
            }
        }

        $schools = ['SMK Negeri 1 Manado', 'SMK Kristen 1 Tomohon', 'SMK Negeri 2 Bitung', 'SMK Trinita Manado', 'SMK Yadika Manado', 'SMK Negeri 1 Tondano'];
        $majors = ['Keperawatan', 'Farmasi', 'Analis Kesehatan', 'Perhotelan'];
        $allergies = [3 => 'Udang dan kepiting', 9 => 'Kacang tanah', 17 => 'Susu sapi (laktosa)', 26 => 'Telur'];
        $firstNames = ['Angel', 'Brian', 'Cindy', 'David', 'Elsa', 'Felix', 'Gaby', 'Hendra', 'Ivana', 'Jeremy', 'Kezia', 'Lukas', 'Marsha', 'Nathan', 'Olivia', 'Petra', 'Queen', 'Rio', 'Stevi', 'Tirza', 'Ursula', 'Valen', 'Wulan', 'Xaverius', 'Yuni', 'Zefanya', 'Aldo', 'Bella', 'Christo', 'Debora', 'Enjel', 'Frangky'];
        $lastNames = ['Rondonuwu', 'Lumowa', 'Sondakh', 'Kalangi', 'Mamahit', 'Pangemanan', 'Tumbelaka', 'Wenas'];

        $number = 0;
        foreach (['en' => 'Kelas Inggris A', 'zh' => 'Kelas Mandarin A', 'ja' => 'Kelas Jepang A', 'de' => 'Kelas Jerman A'] as $code => $className) {
            $language = Language::query()->where('code', $code)->firstOrFail();
            $classroom = Classroom::query()->updateOrCreate(['batch_id' => $batch->id, 'name' => $className], [
                'language_id' => $language->id,
                'level' => match ($code) {
                    'zh' => 'HSK 1', 'ja' => 'N5', 'de' => 'A1', default => 'Dasar'
                },
                'teacher_id' => $teachers[$code]->id,
                'location_id' => $campus->id,
            ]);

            foreach (range(1, 8) as $i) {
                $number++;
                $nis = 'peserta'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                $gender = $number % 2 === 0 ? Gender::Male : Gender::Female;
                $user = $this->account(null, $firstNames[$number - 1].' '.$lastNames[$number % 8], Role::Participant, [
                    'username' => $nis,
                    'phone' => '6281200000'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                ]);
                $user->profile()->updateOrCreate([], [
                    'school_name' => $schools[$number % count($schools)],
                    'major' => $majors[$number % count($majors)],
                    'gender' => $gender,
                    'birth_date' => now()->subYears(17)->subDays($number * 11)->toDateString(),
                    'allergies' => $allergies[$number] ?? null,
                    'medical_history' => $number === 9 ? 'Asma ringan, membawa inhaler' : null,
                    'emergency_contact_name' => 'Orang tua '.$firstNames[$number - 1],
                    'emergency_contact_phone' => '6281300000'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                    'guardian_name' => 'Orang tua '.$firstNames[$number - 1],
                    'guardian_relationship' => $number % 3 === 0 ? GuardianRelationship::Father : GuardianRelationship::Mother,
                    'guardian_phone' => '6281300000'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                    'target_exam' => $language->exam_format,
                ]);
                $classroom->participants()->syncWithoutDetaching([$user->id => ['joined_at' => $batch->start_date]]);

                $dorm = $dorms[$gender->value];
                if (! $user->currentRoomAssignment()->exists()) {
                    $room = $dorm->rooms()->withCount('activeAssignments')->get()->sortBy('active_assignments_count')->first();
                    $room->assignments()->create(['user_id' => $user->id, 'check_in_date' => $batch->start_date]);
                }
            }
        }

        foreach (['Katering Rica-Rica Mama Ully', 'Dapur Woku Tinoor'] as $vendor) {
            Vendor::query()->updateOrCreate(['name' => $vendor], ['contact_person' => 'Ibu '.explode(' ', $vendor)[1], 'phone' => '6281355500'.(str_contains($vendor, 'Rica') ? '01' : '02'), 'address' => 'Manado', 'is_active' => true]);
        }

        // Seeder data contoh fase berikutnya, berurutan (dijalankan bila sudah ada).
        foreach (self::PHASE_SEEDERS as $seeder) {
            if (class_exists($class = 'Database\\Seeders\\Demo\\'.$seeder)) {
                $this->call($class);
            }
        }
    }

    protected function staffAccounts(): void
    {
        $this->account('admin@wokabulari.test', 'Super Admin Wokabulari', Role::SuperAdmin);
        $this->account('pimpinan@wokabulari.test', 'Direktur Poltekkes', Role::Leader);
        $this->account('program@wokabulari.test', 'Admin Program SMK Go Global', Role::ProgramAdmin);

        foreach ([
            'konsumsi@wokabulari.test' => ['Petugas Konsumsi', [StaffUnit::Consumption]],
            'asrama@wokabulari.test' => ['Pengelola Asrama', [StaffUnit::Dormitory]],
            'kesehatan@wokabulari.test' => ['Petugas Kesehatan', [StaffUnit::Health]],
        ] as $email => [$name, $units]) {
            $user = $this->account($email, $name, Role::Staff);
            app(StaffUnitService::class)->sync($user, $units);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function account(?string $email, string $name, Role $role, array $extra = []): User
    {
        $key = $email ? ['email' => $email] : ['username' => $extra['username']];

        $user = User::query()->updateOrCreate($key, array_merge([
            'name' => $name,
            'password' => $this->password,
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => false,
            'consented_at' => now(),
            'consent_version' => config('wokabulari.consent_version'),
        ], $extra));

        $user->syncRoles([$role->value]);

        return $user;
    }
}
