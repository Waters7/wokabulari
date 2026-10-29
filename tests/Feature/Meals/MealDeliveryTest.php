<?php

use App\Actions\Meals\ManageMealSchedules;
use App\Actions\Meals\ReceiveMeal;
use App\Actions\Meals\ReportMealDelivery;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\MealReadyNotification;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    Carbon::setTestNow(Carbon::parse('2026-11-02 11:00', 'Asia/Makassar'));
});

afterEach(fn () => Carbon::setTestNow());

function consumptionStaff(): User
{
    return staff([StaffUnit::Consumption]);
}

function deliveryData(array $overrides = []): array
{
    return $overrides + [
        'vendor_id' => Vendor::factory()->create()->id,
        'date' => '2026-11-02', 'meal_type' => 'lunch', 'ready_time' => '12:15',
        'quantity' => 60, 'unit' => 'box', 'menu' => "Nasi putih\nIkan woku\nTumis kangkung", 'location' => 'Ruang makan asrama putri',
    ];
}

function photo(string $name = 'foto.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 1200, 900);
}

test('tim konsumsi menginput konsumsi dari jadwal: vendor dipilih dan jam siap membuka pengambilan', function () {
    // Unggahan palsu Livewire butuh jam asli (waktu masa depan merusak deteksi jenis berkas).
    Carbon::setTestNow();
    [$planned, $actual] = Vendor::factory()->count(2)->create()->all();
    $schedule = MealSchedule::factory()->create([
        'vendor_id' => $planned->id, 'meal_type' => MealType::Dinner, 'date' => today(),
        'starts_at' => today()->setTime(20, 0), 'ends_at' => today()->setTime(22, 0),
    ]);

    Livewire::actingAs(consumptionStaff())->test('pages::meals.delivery-form', ['scheduleId' => $schedule->id])
        ->set('vendor_id', $actual->id)
        ->set('quantity', 60)
        ->set('unit', 'box')
        ->set('menu', "Nasi putih\nIkan woku")
        ->set('ready_time', '23:50')
        ->set('location', 'Ruang makan asrama putri')
        ->set('menuPhoto', photo('menu.jpg'))
        ->set('totalPhoto', photo('total.jpg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('meals.deliveries.index', ['tanggal' => today()->toDateString()]));

    $delivery = MealDelivery::query()->sole();
    $schedule->refresh();
    expect($delivery->quantityLabel())->toBe('60 dos')
        ->and($delivery->vendor_id)->toBe($actual->id)
        ->and($schedule->vendor_id)->toBe($actual->id)
        ->and($schedule->starts_at->format('H:i'))->toBe('23:50')
        // Lama rentang pengambilan (2 jam) dipertahankan mulai jam siap.
        ->and($schedule->ends_at->format('H:i'))->toBe('01:50')
        ->and($schedule->menu)->toBe("Nasi putih\nIkan woku");

    Storage::disk('private')->assertExists([$delivery->menu_photo_path, $delivery->total_photo_path]);
});

test('input tanpa jadwal membuat jadwal; snack hanya ada bila ditambahkan tim konsumsi', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();

    $delivery = app(ReportMealDelivery::class)->handle(consumptionStaff(), deliveryData(['meal_type' => 'afternoon_snack', 'ready_time' => '15:30']), photo(), photo());

    $schedule = $delivery->schedule;
    expect($schedule->meal_type)->toBe(MealType::AfternoonSnack)
        ->and($schedule->vendor_id)->toBe($delivery->vendor_id)
        ->and($schedule->portions_ordered)->toBe(60)
        ->and($schedule->isOpen())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-02 15:35', 'Asia/Makassar'));
    $signature = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
    expect(app(ReceiveMeal::class)->handle($schedule->fresh(), $classroom->participants->first(), $signature)->exists)->toBeTrue();

    // Salin minggu: makan utama ikut, snack tidak.
    MealSchedule::factory()->create(['date' => '2026-11-03', 'meal_type' => MealType::Lunch]);
    $copied = app(ManageMealSchedules::class)->duplicateWeek(CarbonImmutable::parse('2026-11-02'), consumptionStaff());
    expect($copied)->toBe(1)
        ->and(MealSchedule::query()->whereDate('date', '2026-11-09')->where('meal_type', MealType::AfternoonSnack)->exists())->toBeFalse();
});

test('foto wajib pada input pertama, vendor wajib aktif, dan hanya tim konsumsi yang menginput', function () {
    $staff = consumptionStaff();

    expect(fn () => app(ReportMealDelivery::class)->handle($staff, deliveryData()))
        ->toThrow(InvalidArgumentException::class, 'Foto menu dan foto total');

    $inactive = Vendor::factory()->create(['is_active' => false]);
    expect(fn () => app(ReportMealDelivery::class)->handle($staff, deliveryData(['vendor_id' => $inactive->id]), photo(), photo()))
        ->toThrow(InvalidArgumentException::class, 'vendor yang masih aktif');

    expect(fn () => app(ReportMealDelivery::class)->handle(staff([StaffUnit::Dormitory]), deliveryData(), photo(), photo()))
        ->toThrow(InvalidArgumentException::class, 'Hanya tim konsumsi');

    // Input kedua untuk tanggal & waktu makan yang sama memperbarui catatan yang sama (foto lama dipakai).
    $data = deliveryData();
    $first = app(ReportMealDelivery::class)->handle($staff, $data, photo(), photo());
    $second = app(ReportMealDelivery::class)->handle($staff, ['ready_time' => '12:30'] + $data);
    expect($second->id)->toBe($first->id)->and($second->menu_photo_path)->toBe($first->menu_photo_path)
        ->and(MealSchedule::query()->count())->toBe(1);
});

test('peserta diberi tahu jam dan tempat saat makanan siap, sekali, hanya yang berhak dan belum mengambil', function () {
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(3)->create();
    [$resident, $commuter, $alreadyTook] = $classroom->participants->all();
    $commuter->profile->update(['lives_in_dorm' => false]);

    $delivery = app(ReportMealDelivery::class)->handle(consumptionStaff(), deliveryData(['meal_type' => 'dinner', 'ready_time' => '18:00']), photo(), photo());

    $this->artisan('app:meal-ready-notifications')->assertSuccessful();
    Notification::assertNothingSent();

    Carbon::setTestNow(Carbon::parse('2026-11-02 18:00', 'Asia/Makassar'));
    $delivery->schedule->receipts()->create(['user_id' => $alreadyTook->id, 'signature_path' => 'x.png', 'received_at' => now()]);
    $this->artisan('app:meal-ready-notifications')->assertSuccessful();

    Notification::assertSentTo($resident, MealReadyNotification::class, function (MealReadyNotification $n) {
        return $n->title() === 'Makan malam siap diambil'
            && str_contains($n->body(), 'Mulai 18.00 WITA di Ruang makan asrama putri')
            && str_contains($n->body(), 'Nasi putih, Ikan woku');
    });
    // Makan malam bukan konsumsi peserta tidak asrama (bawaan); yang sudah mengambil tidak diganggu.
    Notification::assertNotSentTo([$commuter, $alreadyTook], MealReadyNotification::class);

    $this->artisan('app:meal-ready-notifications')->assertSuccessful();
    Notification::assertSentToTimes($resident, MealReadyNotification::class, 1);
    expect($delivery->fresh()->notified_at)->not->toBeNull();
});

test('peserta melihat tempat & jam siap di beranda dan halaman konsumsi', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    app(ReportMealDelivery::class)->handle(consumptionStaff(), deliveryData(['ready_time' => '10:45']), photo(), photo());

    $this->actingAs($participant)->get(route('dashboard'))
        ->assertSee('data-test="meal-ready"', false)->assertSee('Makan siang siap diambil')->assertSee('Ruang makan asrama putri');
    $this->actingAs($participant)->get(route('meals.index'))->assertSee('Ruang makan asrama putri');
    $this->actingAs(consumptionStaff())->get(route('meals.monitor'))->assertSee('Diterima 60 dos');
});

test('halaman input konsumsi: makan utama selalu tampil, snack ditambah bila tersedia; tanpa role vendor', function () {
    $staff = consumptionStaff();
    app(ReportMealDelivery::class)->handle($staff, deliveryData(['vendor_id' => Vendor::factory()->create(['name' => 'Dapur Woku Tinoor'])->id]), photo(), photo());

    $this->actingAs($staff)->get(route('meals.deliveries.index', ['tanggal' => '2026-11-02']))->assertOk()
        ->assertSee('Sarapan')->assertSee('Dapur Woku Tinoor')->assertSee('60 dos')
        ->assertSee('Tambahkan hanya bila snack tersedia');
    $this->actingAs($staff)->get(route('meals.deliveries.create', ['tanggal' => '2026-11-02', 'jenis' => 'morning_snack']))->assertOk()->assertSee('Vendor');

    $labels = collect(app(Navigation::class)->sidebar($staff))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($labels)->toContain('Input Konsumsi')
        ->and(collect(app(Navigation::class)->bottom($staff))->pluck('label'))->toContain('Input');

    $this->actingAs(participant())->get(route('meals.deliveries.index'))->assertForbidden();
    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('meals.deliveries.create'))->assertForbidden();
    expect(\Spatie\Permission\Models\Role::query()->where('name', 'vendor')->exists())->toBeFalse();
});
