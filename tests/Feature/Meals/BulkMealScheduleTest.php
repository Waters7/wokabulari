<?php

use App\Actions\Meals\BulkMealSchedules;
use App\Actions\Meals\ExtendMealWindow;
use App\Actions\Meals\ReceiveMeal;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Notifications\MealWindowExtendedNotification;
use App\Services\MealRoster;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(8, 0));
    $classroom = Classroom::factory()->withParticipants(3)->create();
    $classroom->batch->update(['is_active' => true]);
    $this->officer = staff([StaffUnit::Consumption]);
    $this->vendorA = Vendor::factory()->create(['name' => 'Dapur Woku']);
    $this->vendorB = Vendor::factory()->create(['name' => 'Rumah Makan Tinutuan']);
});

function bulkSignature(): string
{
    return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
}

function receiptFor(MealSchedule $schedule): void
{
    $schedule->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);
    app(ReceiveMeal::class)->handle($schedule->fresh(), app(MealRoster::class)->participants()->first(), bulkSignature());
}

test('isi massal: hari terpilih, menu bergilir, porsi otomatis & angka, jadwal yang ada dilewati', function () {
    MealSchedule::factory()->create(['date' => '2026-11-09', 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorA->id, 'menu' => 'Lama']);
    $bulk = app(BulkMealSchedules::class);
    $dates = $bulk->dates('2026-11-09', '2026-11-15', [1, 2, 3, 4, 5]);

    $result = $bulk->fill($dates, [
        'breakfast' => ['vendor_id' => $this->vendorA->id, 'start_time' => '06:00', 'end_time' => '07:30', 'portions_mode' => 'fixed', 'portions' => 20, 'menu' => ''],
        'lunch' => ['vendor_id' => $this->vendorA->id, 'start_time' => '11:30', 'end_time' => '13:30', 'portions_mode' => 'auto', 'portions' => '', 'menu' => "Nasi kuning\nIkan woku\nAyam rica"],
    ], 'skip', $this->officer);

    expect($dates)->toHaveCount(5)
        ->and($result)->toMatchArray(['create' => 9, 'update' => 0, 'skip' => 1, 'total' => 10]);

    $lunch = MealSchedule::query()->where('meal_type', MealType::Lunch)->orderBy('date')->get();
    expect($lunch->pluck('menu')->all())->toBe(['Lama', 'Ikan woku', 'Ayam rica', 'Nasi kuning', 'Ikan woku'])
        ->and($lunch->last()->portions_ordered)->toBe(app(MealRoster::class)->recipients(MealType::Lunch)->count())
        ->and($lunch->last()->ends_at->format('H:i'))->toBe('13:30');

    $breakfast = MealSchedule::query()->where('meal_type', MealType::Breakfast)->get();
    expect($breakfast)->toHaveCount(5)
        ->and($breakfast->pluck('menu')->unique()->all())->toBe([BulkMealSchedules::PLACEHOLDER_MENU])
        ->and($breakfast->pluck('portions_ordered')->unique()->all())->toBe([20])
        ->and(MealSchedule::query()->whereDate('date', '2026-11-14')->exists())->toBeFalse();
});

test('isi massal mode perbarui tidak mengubah jadwal yang sudah ada penerimaannya', function () {
    $signed = MealSchedule::factory()->create(['date' => today(), 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorA->id, 'menu' => 'Sudah diambil']);
    receiptFor($signed);
    $open = MealSchedule::factory()->create(['date' => today()->addDay(), 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorA->id, 'menu' => 'Belum']);
    $bulk = app(BulkMealSchedules::class);

    $result = $bulk->fill($bulk->dates(today()->toDateString(), today()->addDay()->toDateString(), range(1, 7)), [
        'lunch' => ['vendor_id' => $this->vendorA->id, 'start_time' => '12:00', 'end_time' => '13:00', 'portions_mode' => 'fixed', 'portions' => 10, 'menu' => 'Menu baru'],
    ], 'update', $this->officer);

    expect($result)->toMatchArray(['create' => 0, 'update' => 1, 'skip' => 1])
        ->and($signed->fresh()->menu)->toBe('Sudah diambil')
        ->and($open->fresh()->menu)->toBe('Menu baru');
});

test('halaman isi massal: pratinjau, proses, dan rentang lebih dari 62 hari ditolak', function () {
    $page = Livewire::actingAs($this->officer)->test('pages::meals.schedule-bulk')
        ->set('range.from', '2026-11-09')->set('range.to', '2026-11-15')
        ->set('fillTypes', ['lunch'])
        ->set('meals.lunch.vendor_id', (string) $this->vendorB->id)
        ->set('meals.lunch.menu', 'Tinutuan')
        ->assertSeeHtml('data-test="fill-plan"')
        ->assertSee('7 hari');

    $page->call('runFill')->assertHasNoErrors()->assertSee('7 jadwal dibuat');
    expect(MealSchedule::query()->where('vendor_id', $this->vendorB->id)->count())->toBe(7);

    $page->set('range.to', '2027-02-01')->call('runFill')->assertHasErrors('range.to');
    $page->set('range.to', '2026-11-15')->set('fillTypes', [])->call('runFill')->assertHasErrors('fillTypes');
});

test('ubah massal: ganti vendor, geser jam, porsi otomatis, menu; yang sudah ada penerimaan dan bentrok dilewati', function () {
    $schedules = collect(range(0, 2))->map(fn ($i) => MealSchedule::factory()->create([
        'date' => today()->addDays($i), 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorA->id,
        'starts_at' => today()->addDays($i)->setTime(11, 30), 'ends_at' => today()->addDays($i)->setTime(13, 30), 'portions_ordered' => 99,
    ]));
    receiptFor($schedules[0]);
    // Vendor B sudah punya makan siang di hari ketiga → bentrok.
    MealSchedule::factory()->create(['date' => today()->addDays(2), 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorB->id]);

    $bulk = app(BulkMealSchedules::class);
    $query = $bulk->matching($bulk->dates(today()->toDateString(), today()->addDays(2)->toDateString(), range(1, 7)), ['lunch'], $this->vendorA->id);
    $result = $bulk->change($query, ['vendor_id' => $this->vendorB->id, 'time_mode' => 'shift', 'shift_minutes' => 30, 'portions_mode' => 'auto', 'menu' => 'Nasi campur'], $this->officer);

    $changed = $schedules[1]->fresh();
    expect($result['updated'])->toBe(1)->and($result['skipped'])->toBe(2)
        ->and($result['notes'])->toHaveCount(2)
        ->and($changed->vendor_id)->toBe($this->vendorB->id)
        ->and($changed->starts_at->format('H:i'))->toBe('12:00')
        ->and($changed->ends_at->format('H:i'))->toBe('14:00')
        ->and($changed->menu)->toBe('Nasi campur')
        ->and($changed->portions_ordered)->toBe(app(MealRoster::class)->recipients(MealType::Lunch)->count())
        ->and($schedules[0]->fresh()->vendor_id)->toBe($this->vendorA->id)
        ->and($schedules[2]->fresh()->vendor_id)->toBe($this->vendorA->id);
});

test('hapus massal hanya jadwal tanpa penerimaan; isi ulang memulihkan jadwal terhapus tanpa bentrok', function () {
    $keep = MealSchedule::factory()->create(['date' => today(), 'meal_type' => MealType::Dinner, 'vendor_id' => $this->vendorA->id]);
    receiptFor($keep);
    $gone = MealSchedule::factory()->create(['date' => today()->addDay(), 'meal_type' => MealType::Dinner, 'vendor_id' => $this->vendorA->id]);

    $page = Livewire::actingAs($this->officer)->test('pages::meals.schedule-bulk')
        ->set('mode', 'ubah')
        ->set('range.from', today()->toDateString())->set('range.to', today()->addDay()->toDateString())
        ->set('edit.types', ['dinner'])
        ->assertSee('2')
        ->call('runDelete')
        ->assertHasNoErrors();

    expect(MealSchedule::query()->find($keep->id))->not->toBeNull()
        ->and(MealSchedule::query()->find($gone->id))->toBeNull();

    // Isi ulang slot yang sama: jadwal terhapus dipulihkan, bukan galat kunci unik.
    $page->set('mode', 'isi')->set('fillTypes', ['dinner'])->set('meals.dinner.vendor_id', (string) $this->vendorA->id)
        ->set('range.from', today()->addDay()->toDateString())->call('runFill')->assertHasNoErrors();
    expect(MealSchedule::query()->find($gone->id))->not->toBeNull();

    // Perubahan wajib dicentang minimal satu.
    $page->set('mode', 'ubah')->set('edit.changes', [])->call('runChange')->assertHasErrors('edit.changes');
});

test('hanya tim konsumsi yang bisa membuka isi & ubah massal', function () {
    $this->actingAs($this->officer)->get(route('meals.schedules.bulk'))->assertOk()->assertSee('Isi jadwal baru');
    $this->actingAs(teacher())->get(route('meals.schedules.bulk'))->assertForbidden();
    $this->actingAs(participant())->get(route('meals.schedules.bulk'))->assertForbidden();
    $this->actingAs($this->officer)->get(route('meals.schedules'))->assertSee('ubah massal')->assertSee(route('meals.schedules.bulk'), false);
});

test('makanan terlambat: waktu ambil diperpanjang, peserta bisa tanda tangan, yang belum ambil diberi tahu', function () {
    Notification::fake();
    $schedule = MealSchedule::factory()->closed()->create(['meal_type' => MealType::Lunch, 'vendor_id' => $this->vendorA->id]);
    $participant = app(MealRoster::class)->participants()->first();

    expect(fn () => app(ReceiveMeal::class)->handle($schedule, $participant, bulkSignature()))->toThrow(InvalidArgumentException::class);

    Livewire::actingAs($this->officer)->test('meals.extend-window', ['schedule' => $schedule])
        ->call('openForm')
        ->assertSet('open', true)
        ->set('until', '07:00')
        ->call('save')
        ->assertHasErrors('until')
        ->set('until', '10:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('meal-window-extended');

    $schedule->refresh();
    expect($schedule->ends_at->format('H:i'))->toBe('10:00')
        ->and($schedule->extended_by)->toBe($this->officer->id)
        ->and($schedule->late_note)->toBe('Makanan terlambat dari vendor');
    Notification::assertSentTo($participant, MealWindowExtendedNotification::class);

    app(ReceiveMeal::class)->handle($schedule, $participant, bulkSignature());
    expect($schedule->receipts()->count())->toBe(1);

    Livewire::actingAs($this->officer)->test('pages::meals.monitor')->assertSee('Diperpanjang')->assertSee('Makanan terlambat');
});

test('jadwal hari yang sudah lewat tidak bisa diperpanjang dan peserta tidak bisa memperpanjang', function () {
    $past = MealSchedule::factory()->create(['date' => today()->subDay(), 'starts_at' => now()->subDay()->setTime(11, 0), 'ends_at' => now()->subDay()->setTime(12, 0)]);

    expect(fn () => app(ExtendMealWindow::class)->handle($past, $this->officer, '15:00'))->toThrow(InvalidArgumentException::class, 'sudah lewat');

    Livewire::actingAs(participant())->test('meals.extend-window', ['schedule' => MealSchedule::factory()->create()])
        ->call('openForm')->assertForbidden();
});
