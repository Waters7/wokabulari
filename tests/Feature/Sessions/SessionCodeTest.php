<?php

use App\Actions\Sessions\EndSession;
use App\Actions\Sessions\ManageSessionCode;
use App\Actions\Sessions\SubmitSessionCode;
use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\SessionAttendance;
use App\Models\SessionDocumentation;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function sessionSetup(int $participants = 3): array
{
    $classroom = Classroom::factory()->withParticipants($participants)->create();
    $session = LearningSession::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);
    // Dokumentasi mulai & akhir sudah ada agar tes tidak bergantung pada tanggal wajib dokumentasi.
    SessionDocumentation::factory()->for($session, 'session')->create();
    SessionDocumentation::factory()->for($session, 'session')->end()->create();
    $code = app(ManageSessionCode::class)->start($session, $classroom->teacher);

    return [$classroom, $session->fresh(), $code];
}

function attendToday(User $user): void
{
    DailyAttendance::factory()->for($user)->create(['date' => today()]);
}

test('kode sesi 6 karakter tanpa karakter ambigu', function () {
    [, , $code] = sessionSetup();

    expect($code->code)->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/')
        ->and($code->expires_at->diffInMinutes(now(), true))->toBeGreaterThanOrEqual(14);
});

test('peserta yang sudah absen harian berhasil input kode', function () {
    [$classroom, $session, $code] = sessionSetup();
    $participant = $classroom->participants->first();
    attendToday($participant);

    $attendance = app(SubmitSessionCode::class)->handle($participant, strtolower($code->code));

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->learning_session_id)->toBe($session->id);
});

test('kode kedaluwarsa ditolak', function () {
    [$classroom, , $code] = sessionSetup();
    $participant = $classroom->participants->first();
    attendToday($participant);
    $code->update(['expires_at' => now()->subMinute()]);

    app(SubmitSessionCode::class)->handle($participant, $code->code);
})->throws(InvalidArgumentException::class, 'kedaluwarsa');

test('kode lama tidak berlaku setelah diganti', function () {
    [$classroom, $session, $old] = sessionSetup();
    $participant = $classroom->participants->first();
    attendToday($participant);
    app(ManageSessionCode::class)->generate($session, $classroom->teacher);

    app(SubmitSessionCode::class)->handle($participant, $old->code);
})->throws(InvalidArgumentException::class);

test('peserta kelas lain ditolak', function () {
    [, , $code] = sessionSetup();
    $outsider = participant();
    attendToday($outsider);

    app(SubmitSessionCode::class)->handle($outsider, $code->code);
})->throws(InvalidArgumentException::class, 'tidak terdaftar');

test('peserta yang belum absen harian ditolak', function () {
    [$classroom, , $code] = sessionSetup();

    app(SubmitSessionCode::class)->handle($classroom->participants->first(), $code->code);
})->throws(InvalidArgumentException::class, 'Absen harian dulu');

test('tidak bisa input kode dua kali', function () {
    [$classroom, , $code] = sessionSetup();
    $participant = $classroom->participants->first();
    attendToday($participant);
    app(SubmitSessionCode::class)->handle($participant, $code->code);

    app(SubmitSessionCode::class)->handle($participant, $code->code);
})->throws(InvalidArgumentException::class, 'so input');

test('rate limit 5 percobaan gagal per menit dan percobaan gagal dicatat', function () {
    [$classroom] = sessionSetup();
    $participant = $classroom->participants->first();
    RateLimiter::clear('session-code:'.$participant->id);

    foreach (range(1, 5) as $i) {
        try {
            app(SubmitSessionCode::class)->handle($participant, 'ZZZZZZ');
        } catch (InvalidArgumentException) {
        }
    }

    expect(fn () => app(SubmitSessionCode::class)->handle($participant, 'ZZZZZZ'))
        ->toThrow(InvalidArgumentException::class, 'Terlalu banyak percobaan');
    $this->assertDatabaseHas('activity_log', ['description' => 'Percobaan kode sesi gagal']);
});

test('selesai sesi menandai alpa otomatis kecuali yang punya izin disetujui', function () {
    [$classroom, $session, $code] = sessionSetup(3);
    [$present, $sick, $absent] = $classroom->participants()->orderBy('users.id')->get()->all();

    attendToday($present);
    app(SubmitSessionCode::class)->handle($present, $code->code);
    LeaveRequest::factory()->for($sick)->sick()->approved()->create();

    $marked = app(EndSession::class)->handle($session, $classroom->teacher);

    expect($marked)->toBe(2)
        ->and($session->fresh()->status)->toBe(SessionStatus::Completed)
        ->and(SessionAttendance::where('user_id', $sick->id)->first()->status)->toBe(AttendanceStatus::Sick)
        ->and(SessionAttendance::where('user_id', $absent->id)->first()->status)->toBe(AttendanceStatus::Absent)
        ->and($session->codes()->where('is_active', true)->exists())->toBeFalse();
});

test('scheduler menonaktifkan kode kedaluwarsa', function () {
    [, $session, $code] = sessionSetup();
    $code->update(['expires_at' => now()->subSecond()]);

    $this->artisan('app:close-expired-codes')->assertSuccessful();

    expect($code->fresh()->is_active)->toBeFalse();
});

test('halaman input kode memproses kode lewat Livewire', function () {
    [$classroom, , $code] = sessionSetup();
    $participant = $classroom->participants->first();
    attendToday($participant);

    Livewire::actingAs($participant)->test('pages::participant.session-code')
        ->set('code', $code->code)
        ->call('submit')
        ->assertSet('error', null)
        ->assertNotSet('success', null);
});

test('pengajar membuka sesi, mode proyektor, dan monitor', function () {
    [$classroom, $session] = sessionSetup();

    $this->actingAs($classroom->teacher)->get(route('teach.sessions.show', $session))->assertOk()->assertSee($session->topic);
    $this->actingAs($classroom->teacher)->get(route('teach.sessions.projector', $session))->assertOk()->assertSee('Kode kehadiran');
    $this->actingAs(teacher())->get(route('teach.sessions.projector', $session))->assertForbidden();
});
