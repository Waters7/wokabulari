<?php

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\SessionAttendance;
use App\Services\Reports\RunningSessions;
use Livewire\Livewire;

test('dashboard eksekutif menampilkan sesi yang sedang berjalan sesuai filter kelas', function () {
    $this->travelTo(today()->setTime(12, 0));
    $classroom = Classroom::factory()->withParticipants(3)->create(['name' => 'Kelas Mandarin A']);
    $other = Classroom::factory()->create(['name' => 'Kelas Jepang B']);
    $running = LearningSession::factory()->for($classroom)->create([
        'teacher_id' => $classroom->teacher_id, 'topic' => 'Perkenalan diri', 'date' => today(),
        'start_time' => now()->subHours(2)->format('H:i'), 'end_time' => now()->subHour()->format('H:i'),
        'status' => SessionStatus::Ongoing, 'started_at' => now()->subHours(2),
    ]);
    LearningSession::factory()->for($other)->create(['date' => today(), 'status' => SessionStatus::Ongoing, 'started_at' => now(), 'topic' => 'Angka Jepang']);
    LearningSession::factory()->for($classroom)->create(['date' => today(), 'status' => SessionStatus::Scheduled, 'topic' => 'Sesi sore']);
    SessionAttendance::query()->create(['learning_session_id' => $running->id, 'user_id' => $classroom->participants->first()->id, 'status' => AttendanceStatus::Present]);

    $data = app(RunningSessions::class)->overview(classroomId: $classroom->id);
    expect($data['sessions'])->toHaveCount(1)
        ->and($data['sessions'][0]['attended'])->toBe(1)
        ->and($data['sessions'][0]['participants'])->toBe(3)
        ->and($data['sessions'][0]['overtime'])->toBeGreaterThanOrEqual(59)
        ->and($data['today'])->toBe(['scheduled' => 1, 'ongoing' => 1, 'completed' => 0]);

    Livewire::actingAs(leader())->test('leader.running-sessions')
        ->assertSee('Sesi berjalan (2)')
        ->assertSee('Perkenalan diri')
        ->assertSee('Angka Jepang')
        ->assertSee('Hadir 1/3')
        ->assertSee('Lewat jadwal');

    $this->actingAs(leader())->get(route('leader.dashboard'))->assertOk()->assertSee('Sesi berjalan');
});

test('panel sesi berjalan kosong menampilkan pesan', function () {
    Livewire::actingAs(leader())->test('leader.running-sessions')
        ->assertSee('Sesi berjalan (0)')
        ->assertSee('Tidak ada sesi kelas yang sedang berjalan');
});
