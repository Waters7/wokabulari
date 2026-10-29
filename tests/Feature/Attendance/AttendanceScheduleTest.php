<?php

use App\Actions\Attendance\RecordDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\User;
use App\Support\AttendanceSchedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/** Peserta di angkatan dengan jadwal absen: Senin–Jumat, 06.00 dibuka, tepat waktu s.d. 07.00, ditutup 09.00. */
function scheduledParticipant(array $schedule = []): User
{
    config(['wokabulari.face_verification_enabled' => false, 'wokabulari.geofence_enabled' => false]);

    $batch = Batch::factory()->create($schedule + [
        'attendance_days' => [1, 2, 3, 4, 5],
        'attendance_opens_at' => '06:00',
        'attendance_late_after' => '07:00',
        'attendance_closes_at' => '09:00',
    ]);
    $user = User::factory()->participant()->create();
    Classroom::factory()->create(['batch_id' => $batch->id])->participants()->attach($user->id, ['joined_at' => now()]);

    return $user->fresh();
}

function checkIn(User $user, string $at): DailyAttendance
{
    Carbon::setTestNow(Carbon::parse($at, 'Asia/Makassar'));

    return app(RecordDailyAttendance::class)->handle($user, ['device' => 'Pest']);
}

afterEach(fn () => Carbon::setTestNow());

test('absen harian mengikuti hari dan jam yang diatur di angkatan', function () {
    $user = scheduledParticipant();

    // 2026-10-26 = Senin
    expect(checkIn($user, '2026-10-26 06:45')->status)->toBe(AttendanceStatus::Present)
        ->and(checkIn($user, '2026-10-27 07:30')->status)->toBe(AttendanceStatus::Late);

    expect(fn () => checkIn($user, '2026-10-28 05:59'))->toThrow(InvalidArgumentException::class, 'dibuka mulai pukul 06.00')
        ->and(fn () => checkIn($user, '2026-10-28 09:01'))->toThrow(InvalidArgumentException::class, 'sudah ditutup pukul 09.00')
        ->and(fn () => checkIn($user, '2026-10-31 07:00'))->toThrow(InvalidArgumentException::class, 'Hari ini tidak ada absen harian (Senin–Jumat)');
});

test('angkatan tanpa jadwal sendiri memakai pengaturan sistem setiap hari tanpa jam tutup', function () {
    $user = scheduledParticipant(['attendance_days' => null, 'attendance_opens_at' => null, 'attendance_late_after' => null, 'attendance_closes_at' => null]);

    expect(checkIn($user, '2026-11-01 23:00')->status)->toBe(AttendanceStatus::Late)
        ->and(AttendanceSchedule::forBatch(null)->daysLabel())->toBe('Setiap hari');
});

test('label jadwal: rentang hari berurutan atau daftar hari', function () {
    expect((new AttendanceSchedule([1, 2, 3, 4, 5, 6], '05:00', '07:30', null))->daysLabel())->toBe('Senin–Sabtu')
        ->and((new AttendanceSchedule([1, 3, 5], '05:00', '07:30', null))->daysLabel())->toBe('Senin, Rabu, Jumat')
        ->and((new AttendanceSchedule([1, 2, 3], '06:00', '07:00', '09:00'))->hoursLabel())->toBe('06.00–09.00, tepat waktu s.d. 07.00');
});

test('halaman absen menjelaskan hari libur absen dan jam tutup tanpa membuka kamera', function () {
    $user = scheduledParticipant();

    Carbon::setTestNow(Carbon::parse('2026-11-01 08:00', 'Asia/Makassar')); // Minggu
    $this->actingAs($user)->get(route('attendance.daily'))->assertSee('Hari ini tidak ada absen harian')->assertSee('Senin');

    Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Asia/Makassar')); // Senin, lewat jam tutup
    $this->actingAs($user)->get(route('attendance.daily'))->assertSee('Absen hari ini sudah ditutup');
});

test('admin program mengatur hari dan jam absen di form angkatan', function () {
    $batch = Batch::factory()->create();

    Livewire::actingAs(programAdmin())->test('pages::master.batches')
        ->call('edit', $batch->id)
        ->assertSet('form.attendance_days', ['1', '2', '3', '4', '5', '6', '7'])
        ->set('form.attendance_days', ['1', '3', '5'])
        ->set('form.attendance_opens_at', '06:30')
        ->set('form.attendance_late_after', '07:15')
        ->set('form.attendance_closes_at', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    $schedule = $batch->fresh()->attendanceSchedule();
    expect($schedule->days)->toBe([1, 3, 5])
        ->and([$schedule->opensAt, $schedule->lateAfter, $schedule->closesAt])->toBe(['06:30', '07:15', '10:00']);

    // Jam tutup harus setelah batas tepat waktu; minimal satu hari.
    Livewire::actingAs(programAdmin())->test('pages::master.batches')
        ->call('edit', $batch->id)
        ->set('form.attendance_days', [])
        ->set('form.attendance_closes_at', '07:00')
        ->call('save')
        ->assertHasErrors(['form.attendance_days', 'form.attendance_closes_at']);
});
