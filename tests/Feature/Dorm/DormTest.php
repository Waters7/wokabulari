<?php

use App\Actions\Dorm\ConfirmDormAttendance;
use App\Actions\Dorm\ManageDormRequests;
use App\Actions\Dorm\ManageExitPermits;
use App\Actions\Dorm\RecordDormAttendance;
use App\Enums\DormAttendanceStatus;
use App\Enums\DormRequestStatus;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\StaffUnit;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use App\Notifications\DormAbsenceNotification;
use App\Notifications\ExitPermitNotification;
use App\Services\DormRoster;
use App\Services\MealRoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-26 20:30', 'Asia/Makassar'));
});
afterEach(fn () => Carbon::setTestNow());

/** Peserta yang tinggal di kamar asrama (lokasi asrama 1.4612, 124.8236 radius 100 m). */
function dormResident(?DormRoom $room = null): array
{
    $room ??= DormRoom::factory()->create();
    $room->dormitory->location->update(['latitude' => 1.4612, 'longitude' => 124.8236, 'radius_m' => 100]);
    $user = User::factory()->participant()->create();
    $user->profile->update(['face_descriptor' => array_fill(0, 128, 0.1), 'face_enrolled_at' => now()]);
    DormRoomAssignment::factory()->create(['dorm_room_id' => $room->id, 'user_id' => $user->id]);

    return [$user->fresh(), $room->dormitory, $room];
}

function dormPayload(array $overrides = []): array
{
    return array_merge([
        'descriptor' => array_fill(0, 128, 0.1),
        'selfie' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png'))),
        'latitude' => 1.4612,
        'longitude' => 124.8237,
        'accuracy' => 12,
    ], $overrides);
}

function linkGuardian(User $participant): User
{
    $guardian = guardian();
    $participant->allGuardians()->attach($guardian->id, ['relationship' => 'mother', 'verified_at' => now()]);

    return $guardian;
}

test('absen asrama dalam jendela & radius berstatus menunggu konfirmasi, lalu dikonfirmasi pengelola', function () {
    config(['wokabulari.face_verification_enabled' => true, 'wokabulari.geofence_enabled' => true]);
    [$user, $dormitory] = dormResident();

    $attendance = app(RecordDormAttendance::class)->handle($user, dormPayload());

    expect($attendance->status)->toBe(DormAttendanceStatus::PendingConfirmation)
        ->and($attendance->dormitory_id)->toBe($dormitory->id);
    Storage::disk('private')->assertExists($attendance->selfie_path);

    $manager = staff([StaffUnit::Dormitory]);
    expect(app(ConfirmDormAttendance::class)->confirmMany($manager, [$attendance->id]))->toBe(1)
        ->and($attendance->fresh()->status)->toBe(DormAttendanceStatus::Confirmed)
        ->and($attendance->fresh()->confirmed_by)->toBe($manager->id);
});

test('absen asrama di luar radius ditolak dan di luar jendela waktu tidak bisa', function () {
    config(['wokabulari.face_verification_enabled' => true, 'wokabulari.geofence_enabled' => true]);
    [$user] = dormResident();

    $rejected = app(RecordDormAttendance::class)->handle($user, dormPayload(['latitude' => 1.4700]));
    expect($rejected->status)->toBe(DormAttendanceStatus::Rejected)->and($rejected->reject_reason)->toContain('radius');

    Carbon::setTestNow(Carbon::parse('2026-10-26 19:00', 'Asia/Makassar'));
    expect(fn () => app(RecordDormAttendance::class)->handle($user, dormPayload()))
        ->toThrow(InvalidArgumentException::class, 'Absen asrama hanya pukul');
});

test('apel manual dan konfirmasi sekamar lewat halaman pengelola', function () {
    [$first, $dormitory, $room] = dormResident();
    [$second] = dormResident($room);
    $pending = DormAttendance::factory()->create(['user_id' => $first->id, 'dormitory_id' => $dormitory->id]);

    Livewire::actingAs(staff([StaffUnit::Dormitory]))->test('pages::dorm.manage-attendance')
        ->assertSee($first->name)->assertSee($second->name)
        ->call('confirmRoom', $room->id)
        ->call('present', $second->id);

    expect($pending->fresh()->status)->toBe(DormAttendanceStatus::Confirmed)
        ->and(DormAttendance::query()->where('user_id', $second->id)->first()->status)->toBe(DormAttendanceStatus::Confirmed);
});

test('izin keluar jenis pulang butuh persetujuan wali sebelum pengelola', function () {
    [$user] = dormResident();
    $guardian = linkGuardian($user);
    $manager = staff([StaffUnit::Dormitory]);
    $permits = app(ManageExitPermits::class);

    $permit = $permits->submit($user, [
        'type' => ExitPermitType::HomeVisit->value, 'reason' => 'Ulang tahun nenek', 'destination' => 'Tomohon',
        'contact_phone' => '081234567890', 'planned_departure' => now()->addDay()->setTime(9, 0), 'planned_return' => now()->addDays(2)->setTime(17, 0),
    ]);

    expect($permit->guardian_required)->toBeTrue()->and($permit->status)->toBe(ExitPermitStatus::Submitted);
    Notification::assertSentTo($guardian, ExitPermitNotification::class, fn ($n) => $n->event === 'guardian_needed');

    // Pengelola belum boleh memutuskan sebelum wali menyetujui.
    expect(fn () => $permits->decide($permit, $manager, true))->toThrow(InvalidArgumentException::class, 'wali');
    // Wali lain tidak bisa menyetujui.
    expect(fn () => $permits->guardianDecide($permit, guardian(), true))->toThrow(InvalidArgumentException::class);

    $permits->guardianDecide($permit, $guardian, true);
    expect($permit->fresh()->status)->toBe(ExitPermitStatus::GuardianApproved);
    Notification::assertSentTo($manager, ExitPermitNotification::class, fn ($n) => $n->event === 'review_needed');

    $permits->decide($permit->fresh(), $manager, true, 'Hati-hati di jalan');
    expect($permit->fresh()->status)->toBe(ExitPermitStatus::Approved)->and($permit->fresh()->qr_token)->not->toBeNull();
});

test('izin keluar tanpa wali terhubung langsung ke pengelola; batas waktu & kuota ditegakkan', function () {
    [$user] = dormResident();
    $permits = app(ManageExitPermits::class);
    $data = fn (int $hours) => [
        'type' => ExitPermitType::Temporary->value, 'reason' => 'Beli perlengkapan', 'destination' => 'Mega Mall',
        'contact_phone' => '081234567890', 'planned_departure' => now()->addHours($hours), 'planned_return' => now()->addHours($hours + 2),
    ];

    expect(fn () => $permits->submit($user, $data(1)))->toThrow(InvalidArgumentException::class, 'jam sebelum keluar');

    $permit = $permits->submit($user, $data(5));
    expect($permit->guardian_required)->toBeFalse();
    $permits->submit($user, $data(26));
    expect(fn () => $permits->submit($user, $data(30)))->toThrow(InvalidArgumentException::class, 'per minggu');
});

test('QR izin: pindai keluar lalu kembali; melewati rencana kembali menjadi late_return dan wali dikabari', function () {
    [$user] = dormResident();
    $guardian = linkGuardian($user);
    $manager = staff([StaffUnit::Dormitory]);
    $permit = DormExitPermit::factory()->approved()->create(['user_id' => $user->id, 'planned_departure' => now()->subMinutes(10), 'planned_return' => now()->addHour()]);
    $permits = app(ManageExitPermits::class);

    expect($permits->scan($permit->qr_token, $manager)['direction'])->toBe('out')
        ->and($permit->fresh()->status)->toBe(ExitPermitStatus::Out);

    // Peserta yang sedang keluar ditandai di monitor makan & absen asrama.
    expect(app(MealRoster::class)->flags(today(), collect([$user->id]))[$user->id][0])->toContain('Izin keluar');

    Carbon::setTestNow(now()->addHours(2));
    $this->artisan('app:check-late-returns')->assertSuccessful();

    expect($permit->fresh()->status)->toBe(ExitPermitStatus::LateReturn);
    Notification::assertSentTo($guardian, ExitPermitNotification::class, fn ($n) => $n->event === 'late');
    Notification::assertSentTo($manager, ExitPermitNotification::class, fn ($n) => $n->event === 'late');

    expect($permits->scan($permit->qr_token, $manager)['direction'])->toBe('in')
        ->and($permit->fresh()->status)->toBe(ExitPermitStatus::Returned)
        ->and($permit->fresh()->was_late)->toBeTrue();
});

test('token QR tidak ikut terserialisasi dan peserta hanya melihat izinnya sendiri', function () {
    $permit = DormExitPermit::factory()->approved()->create();

    expect($permit->toArray())->not->toHaveKey('qr_token');
    expect(participant()->can('view', $permit))->toBeFalse();
});

test('setelah jam malam pengelola menerima daftar belum absen dan wali dikabari sekali', function () {
    [$user, $dormitory] = dormResident();
    $guardian = linkGuardian($user);
    $manager = staff([StaffUnit::Dormitory]);

    Carbon::setTestNow(Carbon::parse('2026-10-26 22:20', 'Asia/Makassar'));
    $this->artisan('app:dorm-attendance-alerts')->assertSuccessful();
    $this->artisan('app:dorm-attendance-alerts')->assertSuccessful();

    Notification::assertSentTo($manager, DormAbsenceNotification::class);
    Notification::assertSentToTimes($guardian, DormAbsenceNotification::class, 1);

    // Tutup malam: yang tidak absen menjadi "tidak di asrama".
    expect(app(DormRoster::class)->closeNight(today()))->toBe(1)
        ->and(DormAttendance::query()->where('user_id', $user->id)->first()->status)->toBe(DormAttendanceStatus::NotPresent);
});

test('keluhan asrama diproses sampai selesai dengan catatan', function () {
    [$user, , $room] = dormResident();
    $request = app(ManageDormRequests::class)->submit($user, ['category' => 'broken_facility', 'description' => 'Lampu kamar mati']);

    expect($request->dorm_room_id)->toBe($room->id);
    expect(fn () => app(ManageDormRequests::class)->update($request, staff([StaffUnit::Dormitory]), DormRequestStatus::Resolved))
        ->toThrow(InvalidArgumentException::class, 'Catatan');

    app(ManageDormRequests::class)->update($request, staff([StaffUnit::Dormitory]), DormRequestStatus::Resolved, 'Lampu sudah diganti');
    expect($request->fresh()->status)->toBe(DormRequestStatus::Resolved)->and($request->fresh()->resolved_at)->not->toBeNull();
});

test('akses halaman asrama sesuai unit', function () {
    $dormStaff = staff([StaffUnit::Dormitory]);
    foreach (['dorm.manage.attendance', 'dorm.manage.exits', 'dorm.manage.scan', 'dorm.manage.requests', 'dorm.manage.inventory'] as $route) {
        $this->actingAs($dormStaff)->get(route($route))->assertOk();
    }

    $this->actingAs(staff([StaffUnit::Consumption]))->get(route('dorm.manage.exits'))->assertForbidden();
    $this->actingAs(leader())->get(route('dorm.manage.attendance'))->assertForbidden();

    [$resident] = dormResident();
    foreach (['dorm.attendance', 'dorm.exit.index', 'dorm.requests.index', 'health.report'] as $route) {
        $this->actingAs($resident)->get(route($route))->assertOk();
    }
});
