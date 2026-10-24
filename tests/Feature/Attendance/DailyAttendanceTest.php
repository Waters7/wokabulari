<?php

use App\Actions\Attendance\RecordDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Events\DailyAttendanceRecorded;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Kampus contoh: 1.4605, 124.8228 radius 150 m
function attendanceSetup(): array
{
    config(['wokabulari.face_verification_enabled' => true, 'wokabulari.geofence_enabled' => true]);
    Storage::fake('private');

    $location = Location::factory()->create(['latitude' => 1.4605, 'longitude' => 124.8228, 'radius_m' => 150]);
    $classroom = Classroom::factory()->create(['location_id' => $location->id]);
    $user = User::factory()->participant()->create();
    $user->profile->update(['face_descriptor' => array_fill(0, 128, 0.1), 'face_enrolled_at' => now()]);
    $classroom->participants()->attach($user->id, ['joined_at' => now()]);

    return [$user->fresh(), $classroom];
}

function payload(array $overrides = []): array
{
    return array_merge([
        'descriptor' => array_fill(0, 128, 0.1),
        'selfie' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png'))),
        'latitude' => 1.4606,
        'longitude' => 124.8229,
        'accuracy' => 15,
        'device' => 'Pest',
    ], $overrides);
}

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-10-26 07:10', 'Asia/Makassar')));
afterEach(fn () => Carbon::setTestNow());

test('absen di dalam radius dengan wajah cocok tercatat hadir', function () {
    Event::fake([DailyAttendanceRecorded::class]);
    [$user] = attendanceSetup();

    $attendance = app(RecordDailyAttendance::class)->handle($user, payload());

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->distance_m)->toBeLessThan(150)
        ->and($attendance->face_distance)->toBe(0.0)
        ->and($attendance->selfie_path)->not->toBeNull();
    Storage::disk('private')->assertExists($attendance->selfie_path);
    Event::assertDispatched(DailyAttendanceRecorded::class);
});

test('absen di luar radius ditolak dengan alasan', function () {
    [$user] = attendanceSetup();

    $attendance = app(RecordDailyAttendance::class)->handle($user, payload(['latitude' => 1.4705, 'longitude' => 124.8328]));

    expect($attendance->status)->toBe(AttendanceStatus::Rejected)
        ->and($attendance->reject_reason)->toContain('radius');
});

test('akurasi GPS terlalu buruk ditolak', function () {
    [$user] = attendanceSetup();

    $attendance = app(RecordDailyAttendance::class)->handle($user, payload(['accuracy' => 350]));

    expect($attendance->status)->toBe(AttendanceStatus::Rejected)
        ->and($attendance->reject_reason)->toContain('Akurasi');
});

test('wajah tidak cocok ditolak dan boleh mencoba lagi', function () {
    [$user] = attendanceSetup();

    $first = app(RecordDailyAttendance::class)->handle($user, payload(['descriptor' => array_fill(0, 128, -0.1)]));
    expect($first->status)->toBe(AttendanceStatus::Rejected)
        ->and($first->reject_reason)->toContain('Wajah');

    $second = app(RecordDailyAttendance::class)->handle($user, payload());
    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(AttendanceStatus::Present)
        ->and($second->attempts)->toBe(2);
});

test('tidak bisa absen dua kali pada hari yang sama', function () {
    [$user] = attendanceSetup();
    app(RecordDailyAttendance::class)->handle($user, payload());

    app(RecordDailyAttendance::class)->handle($user, payload());
})->throws(InvalidArgumentException::class, 'so absen hari ini');

test('absen lewat jam batas berstatus terlambat', function () {
    [$user] = attendanceSetup();
    Carbon::setTestNow(Carbon::parse('2026-10-26 08:05', 'Asia/Makassar'));

    expect(app(RecordDailyAttendance::class)->handle($user, payload())->status)->toBe(AttendanceStatus::Late);
});

test('absen sebelum jam buka ditolak', function () {
    [$user] = attendanceSetup();
    Carbon::setTestNow(Carbon::parse('2026-10-26 04:00', 'Asia/Makassar'));

    app(RecordDailyAttendance::class)->handle($user, payload());
})->throws(InvalidArgumentException::class, 'dibuka mulai');

test('peserta tanpa wajah referensi diminta mendaftar dulu', function () {
    [$user] = attendanceSetup();
    $user->profile->update(['face_descriptor' => null]);

    app(RecordDailyAttendance::class)->handle($user->fresh(), payload());
})->throws(InvalidArgumentException::class, 'wajah referensi');

test('mode uji tanpa wajah & geofence tetap mencatat hadir', function () {
    [$user] = attendanceSetup();
    config(['wokabulari.face_verification_enabled' => false, 'wokabulari.geofence_enabled' => false]);

    $attendance = app(RecordDailyAttendance::class)->handle($user, payload(['descriptor' => null, 'latitude' => null, 'longitude' => null]));

    expect($attendance->status)->toBe(AttendanceStatus::Present);
});

test('halaman absen mengirim payload lewat Livewire', function () {
    [$user] = attendanceSetup();

    Livewire::actingAs($user)->test('pages::participant.attendance')
        ->call('submit', payload())
        ->assertSet('error', null);

    expect(DailyAttendance::where('user_id', $user->id)->first()->status)->toBe(AttendanceStatus::Present);
});

test('pengajar kelas menandai absen manual dengan alasan dan tercatat di log', function () {
    [$user, $classroom] = attendanceSetup();
    DailyAttendance::factory()->for($user)->rejected()->create(['date' => today()]);

    Livewire::actingAs($classroom->teacher)->test('pages::manage.attendance')
        ->set('date', today()->toDateString())
        ->set('classroom', $classroom->id)
        ->call('openMark', $user->id)
        ->set('markStatus', 'present')
        ->set('markNote', '')
        ->call('mark')
        ->assertHasErrors('markNote')
        ->set('markNote', 'Kamera HP rusak, hadir di kelas')
        ->call('mark')
        ->assertHasNoErrors();

    $attendance = DailyAttendance::where('user_id', $user->id)->first();
    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->approved_by)->toBe($classroom->teacher_id);
    $this->assertDatabaseHas('activity_log', ['description' => 'Menandai absen harian secara manual']);
});

test('pengajar lain tidak bisa menandai peserta kelas lain', function () {
    [$user, $classroom] = attendanceSetup();
    $other = teacher();

    Livewire::actingAs($other)->test('pages::manage.attendance')
        ->set('markUserId', $user->id)
        ->set('markStatus', 'present')
        ->set('markNote', 'Coba-coba')
        ->call('mark')
        ->assertForbidden();
});
