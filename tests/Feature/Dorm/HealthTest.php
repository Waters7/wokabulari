<?php

use App\Actions\Health\HandleHealthReport;
use App\Actions\Health\ReportSickness;
use App\Enums\DormAttendanceStatus;
use App\Enums\HealthReportStatus;
use App\Enums\LeaveType;
use App\Enums\ReviewStatus;
use App\Enums\StaffUnit;
use App\Models\DormAttendance;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\HealthReport;
use App\Models\LeaveRequest;
use App\Models\SpecialMealRequest;
use App\Models\User;
use App\Notifications\EmergencyAlertNotification;
use App\Notifications\HealthCheckReminderNotification;
use App\Notifications\HealthReportedNotification;
use App\Notifications\SpecialMealNeededNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Notification::fake());

function sickResident(?DormRoom $room = null): array
{
    $room ??= DormRoom::factory()->create();
    $user = User::factory()->participant()->create();
    DormRoomAssignment::factory()->create(['dorm_room_id' => $room->id, 'user_id' => $user->id]);

    return [$user->fresh(), $room];
}

function sickData(array $overrides = []): array
{
    return array_merge(['symptoms' => ['fever', 'dizziness'], 'description' => 'Demam sejak sore', 'temperature' => 38.4, 'severity' => 'moderate'], $overrides);
}

test('laporan sakit memberi tahu pihak yang benar dan membuat integrasi otomatis', function () {
    [$user, $room] = sickResident();
    $guardian = guardian();
    $user->allGuardians()->attach($guardian->id, ['relationship' => 'father', 'verified_at' => now()]);
    $dorm = staff([StaffUnit::Dormitory]);
    $health = staff([StaffUnit::Health]);
    $consumption = staff([StaffUnit::Consumption]);
    $teacherUser = teacher();

    $report = app(ReportSickness::class)->handle($user, $user, sickData(['needs_special_meal' => true, 'special_meal_note' => 'Bubur']));

    expect($report->dorm_room_id)->toBe($room->id)->and($report->status)->toBe(HealthReportStatus::Reported);

    Notification::assertSentTo([$dorm, $health], HealthReportedNotification::class);
    Notification::assertSentTo($guardian, HealthReportedNotification::class, fn ($n) => $n->audience === 'guardian');
    Notification::assertSentTo($consumption, SpecialMealNeededNotification::class);
    Notification::assertNotSentTo($consumption, HealthReportedNotification::class);
    Notification::assertNothingSentTo($teacherUser);

    // Izin sakit kelas menunggu konfirmasi petugas, absen asrama "sakit di kamar", makanan khusus.
    $leave = LeaveRequest::query()->where('health_report_id', $report->id)->first();
    expect($leave->type)->toBe(LeaveType::Sick)->and($leave->status)->toBe(ReviewStatus::Pending)
        ->and(DormAttendance::query()->where('user_id', $user->id)->first()->status)->toBe(DormAttendanceStatus::SickInRoom)
        ->and(SpecialMealRequest::query()->where('health_report_id', $report->id)->exists())->toBeTrue();

    app(HandleHealthReport::class)->confirmSickLeave($report, $health);
    expect($leave->fresh()->status)->toBe(ReviewStatus::Approved);
});

test('data medis terenkripsi dan tersembunyi dari serialisasi', function () {
    $report = HealthReport::factory()->create(['description' => 'Riwayat asma']);

    expect($report->toArray())->not->toHaveKey('description')
        ->and(DB::table('health_reports')->value('description'))->not->toContain('asma');
});

test('privasi: pimpinan dan peserta lain tidak bisa melihat detail, wali & petugas bisa', function () {
    [$user] = sickResident();
    $report = app(ReportSickness::class)->handle($user, $user, sickData(['description' => 'Rahasia medis']));
    $guardian = guardian();
    $user->allGuardians()->attach($guardian->id, ['relationship' => 'mother', 'verified_at' => now()]);

    $this->actingAs(leader())->get(route('health.show', $report))->assertForbidden();
    $this->actingAs(leader())->get(route('health.index'))->assertForbidden();
    $this->actingAs(participant())->get(route('health.show', $report))->assertForbidden();
    $this->actingAs(teacher())->get(route('health.show', $report))->assertForbidden();
    $this->actingAs(guardian())->get(route('health.show', $report))->assertForbidden();

    $this->actingAs(staff([StaffUnit::Health]))->get(route('health.show', $report))->assertOk()->assertSee('Rahasia medis');
    $this->actingAs($guardian)->get(route('health.show', $report))->assertOk();
    $this->actingAs($user)->get(route('health.show', $report))->assertOk();
});

test('teman sekamar boleh melapor atas nama, peserta lain kamar tidak', function () {
    [$user, $room] = sickResident();
    [$roommate] = sickResident($room);
    [$stranger] = sickResident();

    expect(app(ReportSickness::class)->handle($user, $roommate, sickData())->reported_by)->toBe($roommate->id);
    expect(fn () => app(ReportSickness::class)->handle($user, $stranger, sickData()))->toThrow(InvalidArgumentException::class);
});

test('tombol darurat: setiap penekanan dicatat, notifikasi dibatasi', function () {
    [$user] = sickResident();
    $health = staff([StaffUnit::Health]);
    $dorm = staff([StaffUnit::Dormitory]);
    RateLimiter::clear('emergency:'.$user->id);

    $first = app(ReportSickness::class)->emergency($user, $user);
    $second = app(ReportSickness::class)->emergency($user, $user);

    expect($second->id)->toBe($first->id)->and($first->is_emergency)->toBeTrue();
    Notification::assertSentToTimes($health, EmergencyAlertNotification::class, 1);
    Notification::assertSentToTimes($dorm, EmergencyAlertNotification::class, 1);
    expect(Activity::query()->where('description', 'Menekan tombol darurat')->count())->toBe(2);
});

test('timeline penanganan mengubah status dan mengirim pengingat pemantauan', function () {
    [$user] = sickResident();
    $officer = staff([StaffUnit::Health]);
    $report = app(ReportSickness::class)->handle($user, $user, sickData());
    $handle = app(HandleHealthReport::class);

    expect(fn () => $handle->addAction($report, $officer, ['action_type' => 'referral']))->toThrow(InvalidArgumentException::class, 'rujukan');

    $handle->addAction($report, $officer, ['action_type' => 'medication', 'medicine' => 'Paracetamol', 'dosage' => '3×1', 'next_check_at' => now()->addMinutes(30)]);
    expect($report->fresh()->status)->toBe(HealthReportStatus::Handled);

    $this->artisan('app:health-check-reminders')->assertSuccessful();
    Notification::assertSentTo($officer, HealthCheckReminderNotification::class);

    $handle->addAction($report->fresh(), $officer, ['action_type' => 'resolved', 'condition_note' => 'Sudah tidak demam']);
    expect($report->fresh()->status)->toBe(HealthReportStatus::Recovered)->and($report->fresh()->resolved_at)->not->toBeNull();
});

test('halaman lapor sakit peserta mengirim laporan', function () {
    [$user] = sickResident();

    Livewire::actingAs($user)->test('pages::health.report')
        ->call('create')
        ->set('form.symptoms', ['cough_cold'])
        ->set('form.severity', 'mild')
        ->call('save')
        ->assertHasNoErrors();

    // "Sejak kapan" yang dikosongkan tidak boleh terisi waktu sekarang.
    expect(HealthReport::query()->where('user_id', $user->id)->first()->started_at)->toBeNull();
});
