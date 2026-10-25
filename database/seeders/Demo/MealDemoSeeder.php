<?php

namespace Database\Seeders\Demo;

use App\Enums\MealType;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\TeacherRating;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use App\Services\MealRoster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh Fase 7: jadwal makan 2 minggu (2 vendor), penerimaan bertanda tangan, rating vendor & pengajar.
 */
class MealDemoSeeder extends Seeder
{
    protected const MENUS = [
        MealType::Breakfast->value => ['Nasi kuning, telur, abon cakalang', 'Bubur Manado (tinutuan), perkedel nike', 'Roti, telur dadar, pisang', 'Nasi goreng, telur mata sapi, kerupuk'],
        MealType::Lunch->value => ['Nasi, ikan woku belanga, sayur kangkung', 'Nasi, ayam rica-rica, tumis buncis', 'Nasi, cakalang fufu, sayur bunga pepaya', 'Nasi, ikan bakar dabu-dabu, sayur bayam'],
        MealType::Dinner->value => ['Nasi, sup brenebon, tempe goreng', 'Nasi, ayam goreng, sayur sop', 'Nasi, ikan kuah asam, tahu bacem', 'Nasi, daging sapi kecap, capcay'],
    ];

    public function run(): void
    {
        $vendors = Vendor::query()->active()->orderBy('id')->take(2)->get();
        if ($vendors->isEmpty() || MealSchedule::query()->exists()) {
            return;
        }

        $creator = User::query()->where('email', 'konsumsi@wokabulari.test')->first();
        $participants = app(MealRoster::class)->participants();
        $signature = $this->demoSignature();
        $start = CarbonImmutable::now()->startOfWeek()->subWeek();

        foreach (range(0, 13) as $offset) {
            $date = $start->addDays($offset);

            foreach (MealType::cases() as $i => $type) {
                // Vendor pertama: sarapan & makan malam; vendor kedua: makan siang.
                $vendor = $type === MealType::Lunch ? $vendors->last() : $vendors->first();
                [$from, $to] = $type->defaultWindow();
                $schedule = MealSchedule::query()->create([
                    'date' => $date,
                    'meal_type' => $type,
                    'vendor_id' => $vendor->id,
                    'menu' => self::MENUS[$type->value][($offset + $i) % 4],
                    'starts_at' => $date->setTimeFromTimeString($from),
                    'ends_at' => $date->setTimeFromTimeString($to),
                    'portions_ordered' => $participants->count(),
                    'created_by' => $creator?->id,
                ]);

                if ($schedule->ends_at->isFuture()) {
                    continue;
                }

                foreach ($participants as $p => $participant) {
                    // ±90% mengambil; sebagian kecil diwakilkan petugas.
                    if (($p * 7 + $offset * 3 + $i) % 11 === 0) {
                        continue;
                    }
                    $proxy = ($p + $offset) % 23 === 0;
                    MealReceipt::query()->create([
                        'meal_schedule_id' => $schedule->id,
                        'user_id' => $participant->id,
                        'signature_path' => $signature,
                        'received_at' => $schedule->starts_at->addMinutes(($p * 3) % 80),
                        'proxy_by' => $proxy ? $creator?->id : null,
                        'note' => $proxy ? 'Sakit di kamar, makanan diantar petugas' : null,
                    ]);
                }
            }
        }

        $this->ratings($participants, $vendors, $start);
    }

    protected function ratings($participants, $vendors, CarbonImmutable $lastWeek): void
    {
        $comments = ['Enak, porsi pas.', 'Sambal roa mantap!', 'Sayur kadang kurang segar.', 'Mohon nasi jangan terlalu lembek.', null, null];
        $teacherComments = ['Penjelasannya jelas dan sabar.', 'Kalau bisa lebih banyak latihan percakapan.', 'Seru, banyak permainan kosakata.', null, null];

        foreach ([$lastWeek, $lastWeek->addWeek()] as $w => $week) {
            foreach ($participants as $p => $participant) {
                // Minggu lalu ±80% mengisi, minggu ini ±40% (sisanya untuk uji pengingat Jumat).
                if (($p + $w) % ($w === 0 ? 5 : 2) === 0) {
                    continue;
                }

                foreach ($vendors as $v => $vendor) {
                    $base = $v === 0 ? 4 : 3;
                    VendorRating::query()->create([
                        'vendor_id' => $vendor->id,
                        'user_id' => $participant->id,
                        'week_start' => $week,
                        'rating' => min(5, $base + ($p % 2)),
                        'taste' => min(5, $base + ($p % 3 === 0 ? 1 : 0)),
                        'portion' => max(2, $base - ($p % 4 === 0 ? 1 : 0)),
                        'cleanliness' => min(5, $base + 1),
                        'punctuality' => $base,
                        'comment' => $comments[($p + $v) % count($comments)],
                    ]);
                }

                $classroom = $participant->classrooms()->first();
                if ($classroom?->teacher_id) {
                    TeacherRating::query()->create([
                        'teacher_id' => $classroom->teacher_id,
                        'user_id' => $participant->id,
                        'classroom_id' => $classroom->id,
                        'week_start' => $week,
                        'rating' => 4 + ($p % 2),
                        'mastery' => 5,
                        'delivery' => 4 + ($p % 2),
                        'discipline' => 4,
                        'interaction' => 3 + ($p % 3 === 0 ? 1 : 2),
                        'comment' => $teacherComments[$p % count($teacherComments)],
                        'is_anonymous' => $p % 3 !== 0,
                    ]);
                }
            }
        }
    }

    /** Satu gambar tanda tangan contoh (dipakai ulang untuk semua penerimaan demo). */
    protected function demoSignature(): string
    {
        $path = 'signatures/demo/contoh.png';
        $image = imagecreatetruecolor(400, 150);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        $ink = imagecolorallocate($image, 18, 59, 45);
        imagesetthickness($image, 3);
        $points = [[30, 100], [70, 40], [100, 110], [140, 50], [170, 95], [220, 60], [260, 105], [310, 45], [370, 90]];
        for ($i = 1; $i < count($points); $i++) {
            imageline($image, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $ink);
        }
        ob_start();
        imagepng($image);
        Storage::disk('private')->put($path, (string) ob_get_clean());
        imagedestroy($image);

        return $path;
    }
}
