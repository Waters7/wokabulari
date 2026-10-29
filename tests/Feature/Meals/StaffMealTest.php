<?php

use App\Actions\Meals\ReceiveMeal;
use App\Actions\Meals\ReportMealDelivery;
use App\Enums\MealType;
use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Notifications\MealReadyNotification;
use App\Services\MealRoster;
use App\Services\Reports\ReportBuilder;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Storage::fake('private'));

afterEach(fn () => Carbon::setTestNow());

function staffSignature(): string
{
    return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
}

function openSchedule(MealType $type): MealSchedule
{
    return MealSchedule::factory()->create(['meal_type' => $type, 'date' => today(), 'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHour()]);
}

test('admin menandai pengajar menerima konsumsi dari Pengguna & Tim', function () {
    $teacher = teacher();

    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('edit', $teacher->id)
        ->set('form.receives_meals', true)
        ->call('save')->assertHasNoErrors();

    expect($teacher->fresh()->receives_meals)->toBeTrue()->and($teacher->fresh()->receivesMeals())->toBeTrue();
});

test('pengajar penerima konsumsi menandatangani penerimaan seperti peserta, sesuai jenis konsumsi tim', function () {
    $teacher = teacher();
    $teacher->forceFill(['receives_meals' => true])->save();
    $lunch = openSchedule(MealType::Lunch);
    $dinner = openSchedule(MealType::Dinner);

    $labels = collect(app(Navigation::class)->sidebar($teacher))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($labels)->toContain('Konsumsi Saya');
    $this->actingAs($teacher)->get(route('meals.index'))->assertOk()->assertDontSee('Rating mingguan');

    Livewire::actingAs($teacher)->test('pages::meals.index')->call('receive', $lunch->id, staffSignature());
    expect($lunch->receipts()->where('user_id', $teacher->id)->exists())->toBeTrue();

    // Makan malam bukan konsumsi tim (bawaan: snack pagi, makan siang, snack sore).
    expect(fn () => app(ReceiveMeal::class)->handle($dinner, $teacher, staffSignature()))
        ->toThrow(InvalidArgumentException::class, 'konsumsi tim pengajar/tim kerja');

    // Pengajar yang tidak ditandai tidak bisa.
    $other = teacher();
    $this->actingAs($other)->get(route('meals.index'))->assertForbidden();
    expect(fn () => app(ReceiveMeal::class)->handle($lunch, $other, staffSignature()))->toThrow(InvalidArgumentException::class, 'Penerima harus');
});

test('tim penerima dihitung di monitor, porsi, notifikasi, dan laporan konsumsi', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-11-02 11:00', 'Asia/Makassar'));
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $teacher = teacher();
    $teacher->forceFill(['receives_meals' => true, 'name' => 'Grace Wuisan'])->save();
    $roster = app(MealRoster::class);

    expect($roster->recipients(MealType::Lunch)->pluck('id'))->toContain($teacher->id)
        ->and($roster->recipients(MealType::Dinner)->pluck('id'))->not->toContain($teacher->id);

    $delivery = app(ReportMealDelivery::class)->handle(staff([StaffUnit::Consumption]), [
        'vendor_id' => Vendor::factory()->create()->id, 'date' => '2026-11-02', 'meal_type' => 'lunch', 'ready_time' => '11:00',
        'quantity' => 3, 'unit' => 'box', 'menu' => 'Nasi kuning', 'location' => 'Aula',
    ], UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'));
    $this->artisan('app:meal-ready-notifications')->assertSuccessful();
    Notification::assertSentTo($teacher, MealReadyNotification::class);

    // Belum mengambil: pengajar tampil di monitor dengan penanda tim.
    Livewire::actingAs(staff([StaffUnit::Consumption]))->withQueryParams(['date' => '2026-11-02'])->test('pages::meals.monitor')
        ->assertSee('Grace Wuisan')->assertSee('Tim pengajar/kerja');

    Carbon::setTestNow(Carbon::parse('2026-11-02 11:10', 'Asia/Makassar'));
    app(ReceiveMeal::class)->handle($delivery->schedule->fresh(), $teacher, staffSignature());

    $report = app(ReportBuilder::class)->build(ReportType::MealReceipts, ReportPeriod::Daily, CarbonImmutable::parse('2026-11-02'), $classroom->batch_id, null);
    $perSchedule = $report['sheets'][0]['rows'][0];
    $perRecipient = collect($report['sheets'][1]['rows'])->firstWhere(1, 'Grace Wuisan');
    expect($perSchedule[5])->toBe(3)->and($perSchedule[6])->toBe(1)
        ->and($perRecipient[2])->toBe('Pengajar')
        ->and(collect($report['sheets'][3]['rows'])->pluck(3))->toContain('Grace Wuisan');
});

test('tim konsumsi mengatur jenis konsumsi tim di jadwal makan', function () {
    Livewire::actingAs(staff([StaffUnit::Consumption]))->test('pages::meals.schedules')
        ->set('staffTypes', ['lunch', 'dinner'])
        ->call('saveNonResident')->assertHasNoErrors();

    expect(app(MealRoster::class)->staffTypes())->toBe([MealType::Lunch, MealType::Dinner]);
});
