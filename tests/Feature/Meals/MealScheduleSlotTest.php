<?php

use App\Actions\Meals\ManageMealSchedules;
use App\Actions\Meals\ReceiveMeal;
use App\Actions\Meals\ReportMealDelivery;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Services\MealRoster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Kunci unik meal_schedules (tanggal, jenis, vendor) juga berlaku untuk jadwal terhapus,
 * dan tanda terima yang terkirim bersamaan tidak boleh berujung galat 500.
 */

beforeEach(function () {
    Storage::fake('private');
    $this->officer = staff([StaffUnit::Consumption]);
    [$this->planned, $this->actual] = Vendor::factory()->count(2)->create()->all();
});

function slotSchedule(Vendor $vendor, array $overrides = []): MealSchedule
{
    return MealSchedule::factory()->create($overrides + [
        'date' => today(), 'meal_type' => MealType::Breakfast, 'vendor_id' => $vendor->id,
        'starts_at' => today()->setTime(6, 0), 'ends_at' => today()->setTime(23, 0),
    ]);
}

function slotSignature(): string
{
    return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
}

test('input konsumsi mengganti vendor walau jadwal vendor itu pernah dihapus (kasus galat 500 sarapan 13 Nov)', function () {
    $deleted = slotSchedule($this->actual);
    $deleted->delete();
    $schedule = slotSchedule($this->planned);

    $delivery = app(ReportMealDelivery::class)->handle($this->officer, [
        'meal_schedule_id' => $schedule->id, 'vendor_id' => $this->actual->id, 'ready_time' => '07:00',
        'quantity' => 40, 'unit' => 'box', 'menu' => "Nasi putih\nTelur balado", 'location' => 'Ruang Pelatihan',
    ], UploadedFile::fake()->image('menu.jpg', 800, 600), UploadedFile::fake()->image('total.jpg', 800, 600));

    expect($delivery->schedule->id)->toBe($schedule->id)
        ->and($schedule->fresh()->vendor_id)->toBe($this->actual->id)
        ->and(MealSchedule::withTrashed()->find($deleted->id))->toBeNull();
});

test('jadwal terhapus yang sudah berisi tanda terima tidak dihapus permanen; pesan jelas, bukan galat 500', function () {
    $deleted = slotSchedule($this->actual);
    app(ReceiveMeal::class)->handle($deleted, app(MealRoster::class)->recipients(MealType::Breakfast)->first() ?? participant(), slotSignature());
    $deleted->delete();
    $schedule = slotSchedule($this->planned);

    expect(fn () => app(ReportMealDelivery::class)->handle($this->officer, [
        'meal_schedule_id' => $schedule->id, 'vendor_id' => $this->actual->id, 'ready_time' => '07:00',
        'quantity' => 40, 'unit' => 'box', 'menu' => 'Nasi', 'location' => 'Ruang Pelatihan',
    ], UploadedFile::fake()->image('menu.jpg'), UploadedFile::fake()->image('total.jpg')))->toThrow(InvalidArgumentException::class, 'pernah dihapus');

    expect(MealSchedule::withTrashed()->find($deleted->id))->not->toBeNull()
        ->and($schedule->fresh()->vendor_id)->toBe($this->planned->id);
});

test('ubah jadwal di form ke vendor yang jadwalnya pernah dihapus tidak galat', function () {
    $deleted = slotSchedule($this->actual);
    $deleted->delete();
    $schedule = slotSchedule($this->planned);

    app(ManageMealSchedules::class)->save([
        'date' => today()->toDateString(), 'meal_type' => 'breakfast', 'vendor_id' => $this->actual->id,
        'menu' => 'Nasi kuning', 'start_time' => '06:00', 'end_time' => '07:30', 'portions_ordered' => 30,
    ], $this->officer, $schedule);

    expect($schedule->fresh()->vendor_id)->toBe($this->actual->id);
});

test('tanda terima yang terkirim bersamaan: kiriman kedua dapat pesan "sudah tercatat" dan berkas tanda tangannya dibuang', function () {
    $schedule = slotSchedule($this->planned);
    $participant = participant();

    // Tiru kiriman lain yang tersimpan tepat setelah pengecekan "sudah tercatat".
    MealReceipt::creating(function (MealReceipt $receipt) {
        MealReceipt::withoutEvents(fn () => MealReceipt::query()->insert([
            'meal_schedule_id' => $receipt->meal_schedule_id, 'user_id' => $receipt->user_id, 'signature_path' => 'signatures/lain.png',
            'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]));
    });

    expect(fn () => app(ReceiveMeal::class)->handle($schedule, $participant, slotSignature()))
        ->toThrow(InvalidArgumentException::class, 'sudah tercatat');

    expect(MealReceipt::query()->where('meal_schedule_id', $schedule->id)->count())->toBe(1)
        ->and(Storage::disk('private')->allFiles())->toBe([]);
});
