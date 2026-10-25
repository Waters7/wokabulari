<?php

use App\Actions\Assignments\GradeSubmission;
use App\Actions\Assignments\SaveAssignment;
use App\Actions\Assignments\SubmitAssignment;
use App\Enums\ComponentSource;
use App\Enums\SubmissionStatus;
use App\Enums\SubmissionType;
use App\Events\AssignmentSubmitted;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use App\Notifications\AssignmentPublishedNotification;
use App\Notifications\AssignmentReminderNotification;
use App\Notifications\SubmissionReviewedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();
});

function assignmentSetup(array $state = []): array
{
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $assignment = Assignment::factory()->for($classroom)->create($state);

    return [$classroom, $assignment, $classroom->participants()->orderBy('users.id')->first()];
}

test('pengumpulan tepat waktu berstatus dikumpulkan dan memicu event', function () {
    Event::fake([AssignmentSubmitted::class]);
    [, $assignment, $participant] = assignmentSetup();

    $submission = app(SubmitAssignment::class)->handle($assignment, $participant, 'Jawaban saya', photos: [UploadedFile::fake()->image('tugas.jpg', 1200, 1600)]);

    expect($submission->status)->toBe(SubmissionStatus::Submitted)
        ->and($submission->is_late)->toBeFalse()
        ->and($submission->files)->toHaveCount(1);
    Storage::disk('private')->assertExists($submission->files->first()->file_path);
    Event::assertDispatched(AssignmentSubmitted::class);
});

test('pengumpulan terlambat diberi potongan nilai saat dinilai', function () {
    [$classroom, $assignment, $participant] = assignmentSetup(['due_at' => now()->subHour(), 'late_penalty_percent' => 20]);

    $submission = app(SubmitAssignment::class)->handle($assignment, $participant, 'Telat sedikit');
    expect($submission->status)->toBe(SubmissionStatus::Late)->and($submission->is_late)->toBeTrue();

    $graded = app(GradeSubmission::class)->grade($submission, $classroom->teacher, 90, 'Bagus');

    expect($graded->score)->toBe(90.0)
        ->and($graded->final_score)->toBe(72.0)
        ->and($graded->status)->toBe(SubmissionStatus::Graded);
    Notification::assertSentTo($participant, SubmissionReviewedNotification::class);
});

test('terlambat ditolak bila tugas tidak mengizinkan', function () {
    [, $assignment, $participant] = assignmentSetup(['due_at' => now()->subMinute(), 'allow_late' => false]);

    app(SubmitAssignment::class)->handle($assignment, $participant, 'Jawaban');
})->throws(InvalidArgumentException::class, 'Tenggat sudah lewat');

test('kumpul ulang hanya bila diizinkan, revisi selalu boleh dan menambah hitungan revisi', function () {
    [$classroom, $assignment, $participant] = assignmentSetup(['allow_resubmit' => false]);
    $submit = app(SubmitAssignment::class);

    $submission = $submit->handle($assignment, $participant, 'Versi 1');
    expect(fn () => $submit->handle($assignment, $participant, 'Versi 2'))->toThrow(InvalidArgumentException::class, 'tidak bisa dikumpul ulang');

    app(GradeSubmission::class)->requestRevision($submission, $classroom->teacher, 'Tambahkan contoh kalimat.');
    expect($submission->fresh()->status)->toBe(SubmissionStatus::RevisionRequested);

    $revised = $submit->handle($assignment, $participant, 'Versi revisi');
    expect($revised->status)->toBe(SubmissionStatus::Submitted)
        ->and($revised->revision_count)->toBe(1)
        ->and($revised->text_answer)->toBe('Versi revisi');
});

test('jenis pengumpulan harus sesuai', function () {
    [, $assignment, $participant] = assignmentSetup(['submission_type' => SubmissionType::Audio]);

    app(SubmitAssignment::class)->handle($assignment, $participant, 'Teks saja');
})->throws(InvalidArgumentException::class, 'tidak sesuai');

test('peserta kelas lain tidak bisa mengumpulkan', function () {
    [, $assignment] = assignmentSetup();

    app(SubmitAssignment::class)->handle($assignment, participant(), 'Jawaban');
})->throws(InvalidArgumentException::class);

test('nilai tugas yang ditautkan ke sesi otomatis masuk komponen sesi (skala 0–100)', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $session = LearningSession::factory()->for($classroom)->create();
    $participant = $classroom->participants->first();

    $assignment = app(SaveAssignment::class)->save($classroom, $classroom->teacher, [
        'learning_session_id' => $session->id, 'title' => 'Menulis paragraf', 'instructions' => 'Tulis 1 paragraf.',
        'submission_type' => 'text', 'aspect' => 'writing', 'due_at' => now()->addDay(), 'max_score' => 50,
        'allow_late' => true, 'late_penalty_percent' => 10, 'allow_resubmit' => false,
    ]);
    app(SaveAssignment::class)->publish($assignment);
    Notification::assertSentTo($participant, AssignmentPublishedNotification::class);

    $submission = app(SubmitAssignment::class)->handle($assignment, $participant, 'Paragraf saya');
    app(GradeSubmission::class)->grade($submission, $classroom->teacher, 40);

    $component = SessionAssessmentComponent::where('source', ComponentSource::Assignment)->where('source_id', $assignment->id)->firstOrFail();
    expect($component->isEditable())->toBeFalse()
        ->and($component->scores()->where('user_id', $participant->id)->value('score'))->toBe(80.0);
});

test('pengingat H-1 dan 2 jam hanya ke yang belum mengumpulkan dan tidak dobel', function () {
    Carbon::setTestNow('2026-11-02 18:00');
    [$classroom, $assignment, $submitted] = assignmentSetup(['due_at' => '2026-11-03 21:00']);
    $pending = $classroom->participants()->where('users.id', '!=', $submitted->id)->first();
    app(SubmitAssignment::class)->handle($assignment, $submitted, 'Sudah');

    $this->artisan('app:assignment-reminders', ['window' => 'day'])->assertSuccessful();
    $this->artisan('app:assignment-reminders', ['window' => 'day'])->assertSuccessful();

    Notification::assertSentToTimes($pending, AssignmentReminderNotification::class, 1);
    Notification::assertNotSentTo($submitted, AssignmentReminderNotification::class);

    Carbon::setTestNow('2026-11-03 19:30');
    $this->artisan('app:assignment-reminders', ['window' => '2h'])->assertSuccessful();
    Notification::assertSentToTimes($pending, AssignmentReminderNotification::class, 2);
    Carbon::setTestNow();
});

test('alur halaman: pengajar membuat & menerbitkan, peserta mengumpulkan, pengajar menilai cepat', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();

    Livewire::actingAs($classroom->teacher)->test('pages::teach.assignments')
        ->call('create')
        ->set('form.classroom_id', $classroom->id)
        ->set('form.title', 'Perkenalan diri')
        ->set('form.instructions', 'Rekam perkenalan diri 1 menit.')
        ->set('form.submission_type', 'mixed')
        ->call('save', true)
        ->assertHasNoErrors();

    $assignment = Assignment::firstOrFail();
    expect($assignment->published_at)->not->toBeNull();

    Livewire::actingAs($participant)->test('pages::participant.assignment-show', ['assignment' => $assignment])
        ->set('text', 'Halo, nama saya…')
        ->set('audio', UploadedFile::fake()->create('rekaman.webm', 50, 'audio/webm'))
        ->call('submit')
        ->assertSet('error', null);

    Livewire::actingAs($classroom->teacher)->test('pages::teach.assignment-show', ['assignment' => $assignment])
        ->set('score', '85')
        ->set('feedback', 'Pelafalan jelas')
        ->call('grade')
        ->assertHasNoErrors();

    expect($assignment->submissions()->first()->final_score)->toBe(85.0);

    $this->actingAs($participant)->get(route('assignments.index'))->assertOk();
    $this->actingAs($participant)->get(route('assignments.show', $assignment))->assertOk()->assertSee('Pelafalan jelas');
});

test('pengajar lain tidak bisa menilai, peserta tidak melihat tugas draf', function () {
    [$classroom, $assignment] = assignmentSetup();
    $draft = Assignment::factory()->for($classroom)->draft()->create();

    $this->actingAs(teacher())->get(route('teach.assignments.show', $assignment))->assertForbidden();
    $this->actingAs($classroom->participants->first())->get(route('assignments.show', $draft))->assertForbidden();
});
