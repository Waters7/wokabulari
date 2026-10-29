<?php

use App\Actions\Meals\ReceiveMeal;
use App\Actions\Meals\ReportMealDelivery;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\MealReadyNotification;
use App\Support\Navigation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

function vendorUser(?Vendor $vendor = null): User
{
    return User::factory()->vendorAccount($vendor ?? Vendor::factory()->create())->create();
}

function deliveryData(array $overrides = []): array
{
    return $overrides + [
        'date' => '2026-11-02', 'meal_type' => 'lunch', 'ready_time' => '12:15',
        'quantity' => 60, 'unit' => 'box', 'menu' => "Nasi putih\nIkan woku\nTumis kangkung", 'location' => 'Ruang makan asrama putri',
    ];
}

function photo(string $name = 'foto.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 1200, 900);
}

test('tim konsumsi membuat akun login vendor dari halaman vendor', function () {
    $vendor = Vendor::factory()->create();

    Livewire::actingAs(staff([StaffUnit::Consumption]))->test('pages::master.vendors')
        ->call('openAccounts', $vendor->id)
        ->set('account.name', 'Ibu Ully')
        ->set('account.phone', '0813-5550-0901')
        ->set('account.password', 'rahasia123')
        ->call('createAccount')
        ->assertHasNoErrors();

    $account = User::query()->where('phone', '6281355500901')->sole();
    expect($account->isVendor())->toBeTrue()
        ->and($account->vendor_id)->toBe($vendor->id)
        ->and($account->must_change_password)->toBeTrue()
        ->and($account->can('meal.deliver'))->toBeTrue();

    // Akun vendor tidak tercampur di Pengguna & Tim.
    Livewire::actingAs(superAdmin())->test('pages::master.users')->assertDontSee('Ibu Ully');
});

test('vendor melapor pengantaran untuk jadwal tim konsumsi: jam siap membuka pengambilan', function () {
    // Unggahan palsu Livewire butuh jam asli (waktu masa depan merusak deteksi jenis berkas).
    Carbon::setTestNow();
    $vendorAccount = vendorUser();
    $schedule = MealSchedule::factory()->create([
        'vendor_id' => $vendorAccount->vendor_id, 'meal_type' => MealType::Dinner, 'date' => today(),
        'starts_at' => today()->setTime(20, 0), 'ends_at' => today()->setTime(22, 0),
    ]);

    Livewire::actingAs($vendorAccount)->test('pages::vendor.delivery-form', ['scheduleId' => $schedule->id])
        ->set('quantity', 60)
        ->set('unit', 'box')
        ->set('menu', "Nasi putih\nIkan woku")
        ->set('ready_time', '23:50')
        ->set('location', 'Ruang makan asrama putri')
        ->set('menuPhoto', photo('menu.jpg'))
        ->set('totalPhoto', photo('total.jpg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('vendor.deliveries.index'));

    $delivery = MealDelivery::query()->sole();
    $schedule->refresh();
    expect($delivery->quantityLabel())->toBe('60 dos')
        ->and($delivery->location)->toBe('Ruang makan asrama putri')
        ->and($schedule->starts_at->format('H:i'))->toBe('23:50')
        // Lama rentang pengambilan (2 jam) dipertahankan mulai jam siap.
        ->and($schedule->ends_at->format('H:i'))->toBe('01:50')
        ->and($schedule->menu)->toBe("Nasi putih\nIkan woku");

    Storage::disk('private')->assertExists([$delivery->menu_photo_path, $delivery->total_photo_path]);
});

test('vendor melapor tanpa jadwal: jadwal makan dibuat dan peserta bisa menandatangani setelah siap', function () {
    $vendorAccount = vendorUser();
    $classroom = Classroom::factory()->withParticipants(1)->create();

    $delivery = app(ReportMealDelivery::class)->handle($vendorAccount, deliveryData(['meal_type' => 'afternoon_snack', 'ready_time' => '15:30']), photo(), photo());

    $schedule = $delivery->schedule;
    expect($schedule->meal_type)->toBe(MealType::AfternoonSnack)
        ->and($schedule->vendor_id)->toBe($vendorAccount->vendor_id)
        ->and($schedule->portions_ordered)->toBe(60)
        ->and($schedule->isOpen())->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-02 15:35', 'Asia/Makassar'));
    $signature = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
    expect(app(ReceiveMeal::class)->handle($schedule->fresh(), $classroom->participants->first(), $signature)->exists)->toBeTrue();
});

test('foto wajib pada laporan pertama dan vendor tidak bisa melapor jadwal vendor lain', function () {
    $vendorAccount = vendorUser();

    expect(fn () => app(ReportMealDelivery::class)->handle($vendorAccount, deliveryData()))
        ->toThrow(InvalidArgumentException::class, 'Foto menu dan foto total');

    $other = MealSchedule::factory()->create(['date' => '2026-11-02']);
    expect(fn () => app(ReportMealDelivery::class)->handle($vendorAccount, deliveryData(['meal_schedule_id' => $other->id]), photo(), photo()))
        ->toThrow(ModelNotFoundException::class);

    // Laporan kedua boleh tanpa foto baru (foto lama dipakai).
    $first = app(ReportMealDelivery::class)->handle($vendorAccount, deliveryData(), photo(), photo());
    $second = app(ReportMealDelivery::class)->handle($vendorAccount, deliveryData(['ready_time' => '12:30']));
    expect($second->id)->toBe($first->id)->and($second->menu_photo_path)->toBe($first->menu_photo_path);
});

test('peserta diberi tahu jam dan tempat saat makanan siap, sekali, hanya yang berhak dan belum mengambil', function () {
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(3)->create();
    [$resident, $commuter, $alreadyTook] = $classroom->participants->all();
    $commuter->profile->update(['lives_in_dorm' => false]);

    $delivery = app(ReportMealDelivery::class)->handle(vendorUser(), deliveryData(['meal_type' => 'dinner', 'ready_time' => '18:00']), photo(), photo());

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
    app(ReportMealDelivery::class)->handle(vendorUser(), deliveryData(['ready_time' => '10:45']), photo(), photo());

    $this->actingAs($participant)->get(route('dashboard'))
        ->assertSee('data-test="meal-ready"', false)->assertSee('Makan siang siap diambil')->assertSee('Ruang makan asrama putri');
    $this->actingAs($participant)->get(route('meals.index'))->assertSee('Ruang makan asrama putri');
    $this->actingAs(staff([StaffUnit::Consumption]))->get(route('meals.monitor'))->assertSee('Vendor melaporkan 60 dos');
});

test('vendor hanya melihat menu pengantaran, bukan pengumuman umum', function () {
    $vendorAccount = vendorUser();
    $vendorAccount->forceFill(['must_change_password' => false])->save();
    Announcement::factory()->create(['title' => 'Info untuk peserta']);

    $labels = collect(app(Navigation::class)->sidebar($vendorAccount))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($labels->all())->toBe(['Beranda', 'Pengantaran', 'Lapor Pengantaran'])
        ->and(Announcement::query()->visibleTo($vendorAccount)->count())->toBe(0)
        ->and(Announcement::query()->first()->recipients()->pluck('id'))->not->toContain($vendorAccount->id);

    $this->actingAs($vendorAccount)->get(route('dashboard'))->assertOk()->assertSee('Lapor pengantaran');
    $this->actingAs($vendorAccount)->get(route('vendor.deliveries.index'))->assertOk();
    $this->actingAs(participant())->get(route('vendor.deliveries.index'))->assertForbidden();
});
