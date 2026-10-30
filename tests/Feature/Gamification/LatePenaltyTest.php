<?php

use App\Actions\Dorm\ConfirmDormAttendance;
use App\Actions\Dorm\RecordDormAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\DormAttendanceStatus;
use App\Enums\PointEvent;
use App\Enums\StaffUnit;
use App\Events\DailyAttendanceRecorded;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\GamificationRule;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Gamification\PointService;
use App\Services\SettingService;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(GamificationSeeder::class);
    Notification::fake();
    Storage::fake('private');
    config(['wokabulari.face_verification_enabled' => true, 'wokabulari.geofence_enabled' => true]);
});
afterEach(fn () => Carbon::setTestNow());

function lateDormResident(): User
{
    $room = DormRoom::factory()->create();
    $room->dormitory->location->update(['latitude' => 1.4612, 'longitude' => 124.8236, 'radius_m' => 100]);
    $user = User::factory()->participant()->create();
    $user->profile->update(['face_descriptor' => array_fill(0, 128, 0.1), 'face_enrolled_at' => now()]);
    DormRoomAssignment::factory()->create(['dorm_room_id' => $room->id, 'user_id' => $user->id]);

    return $user->fresh();
}

function lateDormPayload(): array
{
    return [
        'descriptor' => array_fill(0, 128, 0.1),
        'selfie' => 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png'))),
        'latitude' => 1.4612, 'longitude' => 124.8237, 'accuracy' => 12,
    ];
}

test('aturan pengurang terlambat absen harian & asrama ada di Komponen Poin dan aktif', function () {
    foreach ([PointEvent::DailyAttendanceLate, PointEvent::DormAttendanceLate] as $event) {
        expect(GamificationRule::query()->where('event_key', $event->value)->sole())
            ->points->toBe(-5)->is_active->toBeTrue();
    }

    Livewire::actingAs(programAdmin())->test('pages::master.point-components')
        ->assertSeeInOrder(['Pelanggaran (pengurang poin)', 'Terlambat absen harian', 'Terlambat absen asrama']);
});

test('absen harian terlambat tetap tercatat, peserta diingatkan, dan poin berkurang', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 08:10', 'Asia/Makassar'));
    config(['wokabulari.face_verification_enabled' => false]);
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $classroom->batch->update(['is_active' => true, 'attendance_days' => [1, 2, 3, 4, 5], 'attendance_late_after' => '07:30', 'attendance_closes_at' => null]);
    $participant = $classroom->participants->first();

    // Sebelum absen: halaman menjelaskan bahwa absen sekarang tercatat terlambat & poin berkurang.
    $this->actingAs($participant)->get(route('attendance.daily'))
        ->assertSee('data-test="late-warning"', false)->assertSee('tercatat TERLAMBAT')->assertSee('−5 poin');

    $attendance = DailyAttendance::factory()->create(['user_id' => $participant->id, 'date' => '2026-11-02', 'status' => AttendanceStatus::Late, 'checked_in_at' => now()]);
    event(new DailyAttendanceRecorded($attendance));
    event(new DailyAttendanceRecorded($attendance));

    expect(app(PointService::class)->total($participant))->toBe(-5);
    $this->actingAs($participant)->get(route('attendance.daily'))->assertSee('data-test="late-notice"', false)->assertSee('Tercatat terlambat');

    // Tepat waktu tidak kena pengurangan.
    $onTime = User::factory()->participant()->create();
    event(new DailyAttendanceRecorded(DailyAttendance::factory()->create(['user_id' => $onTime->id, 'date' => '2026-11-02', 'status' => AttendanceStatus::Present, 'checked_in_at' => now()])));
    expect(app(PointService::class)->total($onTime))->toBe(10);
});

test('absen asrama setelah jendela tetap bisa sampai batas terlambat; poin berkurang saat dikonfirmasi', function () {
    $user = lateDormResident();

    // 22:10: lewat jendela (s.d. 21:30) tetapi sebelum batas terlambat 23:59.
    Carbon::setTestNow(Carbon::parse('2026-10-26 22:10', 'Asia/Makassar'));
    $this->actingAs($user)->get(route('dorm.attendance'))->assertSee('data-test="late-warning"', false)->assertSee('tercatat TERLAMBAT');

    $attendance = app(RecordDormAttendance::class)->handle($user, lateDormPayload());
    expect($attendance)->status->toBe(DormAttendanceStatus::PendingConfirmation)->is_late->toBeTrue();

    app(ConfirmDormAttendance::class)->confirmMany(staff([StaffUnit::Dormitory]), [$attendance->id]);
    expect(app(PointService::class)->total($user))->toBe(-5)
        ->and(PointTransaction::query()->where('user_id', $user->id)->value('event_key'))->toBe(PointEvent::DormAttendanceLate);

    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('dorm.manage.attendance', ['date' => '2026-10-26']))->assertSee('Terlambat');
});

test('lewat batas terlambat asrama absen ditolak; batasnya diatur di Pengaturan Sistem', function () {
    app(SettingService::class)->setMany(['dorm.late_until' => '22:30']);
    $user = lateDormResident();

    Carbon::setTestNow(Carbon::parse('2026-10-26 22:45', 'Asia/Makassar'));
    expect(fn () => app(RecordDormAttendance::class)->handle($user, lateDormPayload()))
        ->toThrow(InvalidArgumentException::class, 'terlambat s.d. 22.30');
});

test('riwayat poin peserta bisa difilter pengurangan dan menampilkan total pengurangan minggu ini', function () {
    $participant = participant();
    app(PointService::class)->adjust($participant, 15, 'Juara lomba pidato', programAdmin());
    app(PointService::class)->award($participant, PointEvent::DailyAttendanceLate, ['test', 1], description: 'Terlambat absen harian');

    Livewire::actingAs($participant)->test('pages::participant.points')
        ->assertSee('data-test="week-penalty"', false)->assertSee('Pengurangan minggu ini: -5 poin')
        ->set('kind', 'minus')
        ->assertSee('Terlambat absen harian')->assertDontSee('Juara lomba pidato')
        ->set('kind', 'plus')
        ->assertSee('Juara lomba pidato')->assertDontSee('Terlambat absen harian');
});
