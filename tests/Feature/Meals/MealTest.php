<?php

use App\Actions\Meals\ManageMealSchedules;
use App\Actions\Meals\ReceiveMeal;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\LeaveRequest;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Services\MealRoster;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
});

function signatureUri(): string
{
    return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
}

test('peserta menandatangani penerimaan dalam rentang waktu dan tersimpan di disk privat', function () {
    $schedule = MealSchedule::factory()->create();
    $participant = participant();

    $receipt = app(ReceiveMeal::class)->handle($schedule, $participant, signatureUri());

    expect($receipt->isProxy())->toBeFalse()->and($receipt->received_at)->not->toBeNull();
    Storage::disk('private')->assertExists($receipt->signature_path);
});

test('tanda tangan di luar jendela waktu ditolak', function (string $state) {
    $schedule = MealSchedule::factory()->{$state}()->create();

    expect(fn () => app(ReceiveMeal::class)->handle($schedule, participant(), signatureUri()))
        ->toThrow(InvalidArgumentException::class, 'Di luar waktu pengambilan');
    expect(MealReceipt::query()->count())->toBe(0);
})->with(['closed', 'upcoming']);

test('penerimaan hanya sekali per jadwal', function () {
    $schedule = MealSchedule::factory()->create();
    $participant = participant();
    app(ReceiveMeal::class)->handle($schedule, $participant, signatureUri());

    expect(fn () => app(ReceiveMeal::class)->handle($schedule, $participant, signatureUri()))
        ->toThrow(InvalidArgumentException::class, 'sudah tercatat');
});

test('petugas mencatat penerimaan diwakilkan setelah jendela ditutup dengan catatan wajib', function () {
    $schedule = MealSchedule::factory()->closed()->create();
    $participant = participant();
    $officer = staff([StaffUnit::Consumption]);

    expect(fn () => app(ReceiveMeal::class)->handle($schedule, $participant, signatureUri(), $officer, ''))
        ->toThrow(InvalidArgumentException::class, 'Catatan wajib');

    $receipt = app(ReceiveMeal::class)->handle($schedule, $participant, signatureUri(), $officer, 'Sakit di kamar');

    expect($receipt->proxy_by)->toBe($officer->id)->and($receipt->note)->toBe('Sakit di kamar');
});

test('monitor makan: petugas konsumsi mewakilkan penerimaan lewat halaman monitor', function () {
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $classroom->batch->update(['is_active' => true]);
    $schedule = MealSchedule::factory()->create();
    [$first, $second] = $classroom->participants()->orderBy('users.id')->get();
    app(ReceiveMeal::class)->handle($schedule, $first, signatureUri());

    $officer = staff([StaffUnit::Consumption]);

    Livewire::actingAs($officer)->test('pages::meals.monitor')
        ->assertSee($second->name)
        ->call('openProxy', $second->id)
        ->call('proxyReceive', $schedule->id, $second->id, signatureUri())
        ->assertHasErrors('proxyNote')
        ->set('proxyNote', 'Sakit, diantar ke kamar')
        ->call('proxyReceive', $schedule->id, $second->id, signatureUri())
        ->assertHasNoErrors();

    expect(MealReceipt::query()->where('user_id', $second->id)->value('proxy_by'))->toBe($officer->id);
});

test('peserta tidak bisa membuka monitor atau mewakilkan penerimaan', function () {
    $schedule = MealSchedule::factory()->create();
    $participant = participant();

    $this->actingAs($participant)->get(route('meals.monitor'))->assertForbidden();

    Livewire::actingAs(staff([StaffUnit::Dormitory]))->test('pages::meals.index')
        ->call('receive', $schedule->id, signatureUri())
        ->assertForbidden();
});

test('halaman konsumsi peserta menampilkan jadwal aktif dan menerima tanda tangan', function () {
    $schedule = MealSchedule::factory()->create(['menu' => 'Nasi ikan woku']);
    $participant = participant();

    Livewire::actingAs($participant)->test('pages::meals.index')
        ->assertSee('Nasi ikan woku')
        ->call('receive', $schedule->id, signatureUri());

    expect(MealReceipt::query()->where('user_id', $participant->id)->exists())->toBeTrue();
});

test('jadwal makan dibuat lewat halaman jadwal dan disalin ke minggu berikutnya', function () {
    $vendor = Vendor::factory()->create();
    $officer = staff([StaffUnit::Consumption]);
    $monday = now()->startOfWeek();

    Livewire::actingAs($officer)->test('pages::meals.schedules')
        ->call('create', $monday->toDateString(), MealType::Dinner->value)
        ->assertSet('form.start_time', '17:30')
        ->set('form.vendor_id', $vendor->id)
        ->set('form.menu', 'Nasi, ayam rica')
        ->call('save')
        ->assertHasNoErrors()
        ->call('duplicate');

    expect(MealSchedule::query()->count())->toBe(2)
        ->and(MealSchedule::query()->whereDate('date', $monday->addWeek())->first()->starts_at->format('H:i'))->toBe('17:30');

    // Salinan kedua tidak membuat jadwal ganda.
    expect(app(ManageMealSchedules::class)->duplicateWeek($monday, $officer))->toBe(0);
});

test('jadwal ganda untuk vendor dan waktu makan yang sama ditolak', function () {
    $schedule = MealSchedule::factory()->create();

    expect(fn () => app(ManageMealSchedules::class)->save([
        'date' => $schedule->date->toDateString(), 'meal_type' => $schedule->meal_type->value, 'vendor_id' => $schedule->vendor_id,
        'menu' => 'Lain', 'start_time' => '11:00', 'end_time' => '12:00', 'portions_ordered' => 10,
    ], staff()))->toThrow(InvalidArgumentException::class, 'sudah ada');
});

test('jadwal yang sudah ada penerimaannya tidak bisa dihapus', function () {
    $schedule = MealSchedule::factory()->create();
    app(ReceiveMeal::class)->handle($schedule, participant(), signatureUri());

    Livewire::actingAs(superAdmin())->test('pages::meals.schedules')->call('delete', $schedule->id);

    expect($schedule->fresh()->trashed())->toBeFalse();
});

test('roster konsumsi menandai peserta yang izin sakit', function () {
    $participant = participant();
    LeaveRequest::factory()->sick()->approved()->for($participant)->create();

    $flags = app(MealRoster::class)->flags(today(), collect([$participant->id]));

    expect($flags[$participant->id][0])->toContain('kelas');
});

test('akses halaman konsumsi sesuai unit dan role', function () {
    $consumption = staff([StaffUnit::Consumption]);
    $this->actingAs($consumption)->get(route('meals.schedules'))->assertOk();
    $this->actingAs($consumption)->get(route('meals.monitor'))->assertOk();
    $this->actingAs($consumption)->get(route('meals.allergies'))->assertOk();
    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('meals.schedules'))->assertForbidden();
    $this->actingAs(leader())->get(route('meals.ratings'))->assertOk();
    $this->actingAs(leader())->get(route('meals.schedules'))->assertForbidden();
    $this->actingAs(participant())->get(route('meals.index'))->assertOk();
});
