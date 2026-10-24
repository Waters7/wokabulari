<?php

use App\Actions\Leave\ReviewLeaveRequest;
use App\Actions\Sessions\ScheduleSessions;
use App\Enums\AttendanceStatus;
use App\Enums\ReviewStatus;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\SessionAttendance;
use App\Models\SessionMaterial;
use App\Notifications\LeaveRequestReviewed;
use App\Notifications\MaterialPublished;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('jadwal berulang Senin–Jumat selama 2 minggu membuat 10 sesi', function () {
    $classroom = Classroom::factory()->create();

    $sessions = app(ScheduleSessions::class)->handle($classroom, [
        'date' => '2026-11-02', // Senin
        'start_time' => '08:00',
        'end_time' => '10:00',
        'topic' => 'Percakapan',
        'repeat' => true,
        'weekdays' => [1, 2, 3, 4, 5],
        'weeks' => 2,
    ]);

    expect($sessions)->toHaveCount(10)
        ->and($sessions->every(fn ($s) => ! $s->date->isWeekend()))->toBeTrue();
});

test('pengajar menjadwalkan sesi lewat halaman', function () {
    $classroom = Classroom::factory()->create();

    Livewire::actingAs($classroom->teacher)->test('pages::teach.sessions')
        ->call('create')
        ->set('form.classroom_id', $classroom->id)
        ->set('form.topic', 'Angka & Waktu')
        ->set('form.end_time', '07:00')
        ->call('save')
        ->assertHasErrors('form.end_time')
        ->set('form.end_time', '10:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(LearningSession::where('topic', 'Angka & Waktu')->count())->toBe(1);
});

test('pengajar mengunggah materi dan peserta mendapat notifikasi', function () {
    Storage::fake('private');
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $session = LearningSession::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);

    Livewire::actingAs($classroom->teacher)->test('session.materials-panel', ['session' => $session])
        ->call('create')
        ->set('form.title', 'Kosakata Rumah Sakit')
        ->set('file', UploadedFile::fake()->create('kosakata.pdf', 120, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $material = SessionMaterial::firstOrFail();
    Storage::disk('private')->assertExists($material->file_path);
    Notification::assertSentTo($classroom->participants, MaterialPublished::class);
});

test('peserta hanya bisa membuka materi kelasnya yang sudah tampil, lewat signed URL', function () {
    Storage::fake('private');
    Storage::disk('private')->put('materials/x.pdf', '%PDF-1.4 contoh');
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $session = LearningSession::factory()->for($classroom)->create();
    $participant = $classroom->participants->first();

    $visible = SessionMaterial::factory()->for($session, 'session')->file('materials/x.pdf')->create();
    $hidden = SessionMaterial::factory()->for($session, 'session')->file('materials/x.pdf')->afterSession()->create();

    $this->actingAs($participant)->get($visible->fileUrl())->assertOk();
    $this->actingAs($participant)->get(route('materials.file', $visible))->assertForbidden(); // tanpa tanda tangan
    $this->actingAs($participant)->get($hidden->fileUrl())->assertForbidden();
    $this->actingAs(participant())->get($visible->fileUrl())->assertForbidden();

    $this->assertDatabaseHas('material_access_logs', ['session_material_id' => $visible->id, 'user_id' => $participant->id]);
});

test('izin sakit disetujui mengisi status sakit di absen harian dan sesi', function () {
    Notification::fake();
    Carbon::setTestNow('2026-11-03 09:00');
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $session = LearningSession::factory()->for($classroom)->create(['date' => '2026-11-04']);
    DailyAttendance::factory()->for($participant)->create(['date' => '2026-11-03', 'status' => AttendanceStatus::Present]);

    $leave = LeaveRequest::factory()->for($participant)->sick()->create(['date_from' => '2026-11-03', 'date_to' => '2026-11-04']);

    app(ReviewLeaveRequest::class)->handle($leave, $classroom->teacher, true);

    expect($leave->fresh()->status)->toBe(ReviewStatus::Approved)
        // Hari yang sudah hadir tidak ditimpa
        ->and(DailyAttendance::where('user_id', $participant->id)->whereDate('date', '2026-11-03')->first()->status)->toBe(AttendanceStatus::Present)
        ->and(DailyAttendance::where('user_id', $participant->id)->whereDate('date', '2026-11-04')->first()->status)->toBe(AttendanceStatus::Sick)
        ->and(SessionAttendance::where('learning_session_id', $session->id)->first()->status)->toBe(AttendanceStatus::Sick);

    Notification::assertSentTo($participant, LeaveRequestReviewed::class);
    Carbon::setTestNow();
});

test('penolakan izin wajib beralasan dan hanya pengajar kelasnya yang bisa meninjau', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $leave = LeaveRequest::factory()->for($classroom->participants->first())->create();

    expect(teacher()->can('review', $leave))->toBeFalse()
        ->and($classroom->teacher->can('review', $leave))->toBeTrue();

    app(ReviewLeaveRequest::class)->handle($leave, $classroom->teacher, false);
})->throws(InvalidArgumentException::class, 'Alasan penolakan');

test('peserta mengajukan izin dengan lampiran', function () {
    Storage::fake('private');
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();

    Livewire::actingAs($participant)->test('pages::participant.leave')
        ->call('create')
        ->set('form.type', 'sick')
        ->set('form.reason', 'Demam tinggi sejak semalam')
        ->set('attachment', UploadedFile::fake()->image('surat.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $leave = LeaveRequest::firstOrFail();
    expect($leave->attachment_path)->not->toBeNull();
    Storage::disk('private')->assertExists($leave->attachment_path);
});

test('halaman fase 3 tampil sesuai role', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();

    foreach (['participant.class', 'participant.dorm', 'sessions.code', 'materials.index', 'leave.index', 'notifications.index'] as $route) {
        $this->actingAs($participant)->get(route($route))->assertOk();
    }

    foreach (['teach.sessions.index', 'teach.materials.bank', 'manage.leave.index', 'manage.attendance.index'] as $route) {
        $this->actingAs($classroom->teacher)->get(route($route))->assertOk();
        $this->actingAs($participant)->get(route($route))->assertForbidden();
    }
});
