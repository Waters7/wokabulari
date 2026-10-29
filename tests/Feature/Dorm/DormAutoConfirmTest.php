<?php

use App\Actions\Dorm\RecordDormAttendance;
use App\Enums\DormAttendanceStatus;
use App\Enums\StaffUnit;
use App\Events\DormAttendanceConfirmed;
use App\Models\DormAttendance;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-26 20:30', 'Asia/Makassar'));
    config(['wokabulari.face_verification_enabled' => true, 'wokabulari.geofence_enabled' => true]);
});
afterEach(fn () => Carbon::setTestNow());

function autoDormResident(?DormRoom $room = null): User
{
    $room ??= DormRoom::factory()->create();
    $room->dormitory->location->update(['latitude' => 1.4612, 'longitude' => 124.8236, 'radius_m' => 100]);
    $user = User::factory()->participant()->create();
    $user->profile->update(['face_descriptor' => array_fill(0, 128, 0.1), 'face_enrolled_at' => now()]);
    DormRoomAssignment::factory()->create(['dorm_room_id' => $room->id, 'user_id' => $user->id]);

    return $user->fresh();
}

function autoDormPayload(array $overrides = []): array
{
    return array_merge([
        'descriptor' => array_fill(0, 128, 0.1),
        'selfie' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png'))),
        'latitude' => 1.4612, 'longitude' => 124.8237, 'accuracy' => 12,
    ], $overrides);
}

function setAutoConfirm(bool $on)
{
    return Livewire::actingAs(superAdmin())->test('pages::system.settings')
        ->set('values.dorm.auto_confirm', $on)
        ->call('save')->assertHasNoErrors();
}

test('bawaan mati: absen asrama menunggu konfirmasi pengelola', function () {
    expect((bool) setting('dorm.auto_confirm'))->toBeFalse();

    $attendance = app(RecordDormAttendance::class)->handle(autoDormResident(), autoDormPayload());

    expect($attendance->status)->toBe(DormAttendanceStatus::PendingConfirmation);
    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('dorm.manage.attendance'))->assertDontSee('data-test="dorm-auto-confirm"', false);
});

test('dinyalakan: absen yang menunggu ikut dikonfirmasi dan absen baru langsung terkonfirmasi otomatis', function () {
    Event::fake([DormAttendanceConfirmed::class]);
    $room = DormRoom::factory()->create();
    $waiting = app(RecordDormAttendance::class)->handle(autoDormResident($room), autoDormPayload());
    expect($waiting->status)->toBe(DormAttendanceStatus::PendingConfirmation);

    setAutoConfirm(true);
    expect($waiting->fresh())->status->toBe(DormAttendanceStatus::Confirmed)->confirmed_by->toBeNull()->confirmed_at->not->toBeNull();

    $user = autoDormResident($room);
    $attendance = app(RecordDormAttendance::class)->handle($user, autoDormPayload());
    expect($attendance->status)->toBe(DormAttendanceStatus::Confirmed)->and($attendance->confirmed_by)->toBeNull();
    Event::assertDispatchedTimes(DormAttendanceConfirmed::class, 2);

    // Gagal verifikasi tetap ditolak, tidak ikut dikonfirmasi.
    $rejected = app(RecordDormAttendance::class)->handle(autoDormResident($room), autoDormPayload(['latitude' => 1.4700]));
    expect($rejected->status)->toBe(DormAttendanceStatus::Rejected);

    Livewire::actingAs(staff([StaffUnit::Dormitory]))->test('pages::dorm.manage-attendance', ['dormitoryId' => $room->dormitory_id])
        ->assertSee('Konfirmasi otomatis aktif')->assertSee('otomatis');
});

test('dimatikan lagi: pengelola asrama kembali mengonfirmasi manual', function () {
    setAutoConfirm(true);
    setAutoConfirm(false);

    $attendance = app(RecordDormAttendance::class)->handle(autoDormResident(), autoDormPayload());
    expect($attendance->status)->toBe(DormAttendanceStatus::PendingConfirmation)
        ->and(DormAttendance::query()->where('status', DormAttendanceStatus::Confirmed)->count())->toBe(0);

    $this->actingAs(teacher())->get(route('system.settings'))->assertForbidden();
});
