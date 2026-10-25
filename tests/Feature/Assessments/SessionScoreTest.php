<?php

use App\Actions\Assessments\ManageComponents;
use App\Actions\Assessments\SaveSessionScore;
use App\Actions\Assessments\SyncSourceComponent;
use App\Enums\ComponentSource;
use App\Enums\LanguageAspect;
use App\Models\AssessmentComponentTemplate;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use App\Models\SessionScore;
use App\Services\SessionScoreCalculator;
use Livewire\Livewire;

function assessedSession(int $participants = 2): array
{
    $classroom = Classroom::factory()->withParticipants($participants)->create();
    $session = LearningSession::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);

    return [$classroom, $session];
}

test('nilai akhir sesi adalah rata-rata tertimbang yang dinormalisasi 0–100', function () {
    [$classroom, $session] = assessedSession(1);
    $participant = $classroom->participants->first();

    // Speaking 40% (maks 50), Role play 30% (maks 100), Partisipasi 30% (maks 10)
    $speaking = SessionAssessmentComponent::factory()->for($session, 'session')->create(['weight' => 40, 'max_score' => 50]);
    $roleplay = SessionAssessmentComponent::factory()->for($session, 'session')->create(['weight' => 30, 'max_score' => 100]);
    $participation = SessionAssessmentComponent::factory()->for($session, 'session')->create(['weight' => 30, 'max_score' => 10, 'aspect' => LanguageAspect::Participation]);

    $save = app(SaveSessionScore::class);
    $save->handle($speaking, $participant, '40');     // 80
    $save->handle($roleplay, $participant, '70');     // 70
    $save->handle($participation, $participant, '9'); // 90

    $final = app(SessionScoreCalculator::class)->forSession($session)[$participant->id];

    // (80×40 + 70×30 + 90×30) / 100 = 80
    expect($final)->toBe(80.0);
});

test('komponen tanpa skor tidak ikut dihitung', function () {
    [$classroom, $session] = assessedSession(1);
    $participant = $classroom->participants->first();
    $a = SessionAssessmentComponent::factory()->for($session, 'session')->create(['weight' => 50]);
    SessionAssessmentComponent::factory()->for($session, 'session')->create(['weight' => 50]);

    app(SaveSessionScore::class)->handle($a, $participant, '60');

    expect(app(SessionScoreCalculator::class)->forSession($session)[$participant->id])->toBe(60.0);
});

test('skor di luar rentang, bukan angka, atau peserta kelas lain ditolak', function (mixed $value, string $message) {
    [$classroom, $session] = assessedSession(1);
    $component = SessionAssessmentComponent::factory()->for($session, 'session')->create(['max_score' => 20]);

    app(SaveSessionScore::class)->handle($component, $classroom->participants->first(), $value);
})->with([
    'terlalu besar' => ['21', '0–20'],
    'negatif' => ['-1', '0–20'],
    'bukan angka' => ['abc', 'angka'],
])->throws(InvalidArgumentException::class);

test('koma desimal diterima dan nilai kosong menghapus skor', function () {
    [$classroom, $session] = assessedSession(1);
    $participant = $classroom->participants->first();
    $component = SessionAssessmentComponent::factory()->for($session, 'session')->create();

    expect(app(SaveSessionScore::class)->handle($component, $participant, '87,5')->score)->toBe(87.5);

    app(SaveSessionScore::class)->handle($component, $participant, '');
    expect(SessionScore::count())->toBe(0);
});

test('komponen otomatis dari quiz/tugas tidak bisa diedit manual', function () {
    [$classroom, $session] = assessedSession(1);
    $component = app(SyncSourceComponent::class)->ensure($session, ComponentSource::Quiz, 99, 'Quiz Kosakata', LanguageAspect::Vocabulary);

    expect(fn () => app(SaveSessionScore::class)->handle($component, $classroom->participants->first(), '80'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ManageComponents::class)->delete($component))
        ->toThrow(InvalidArgumentException::class);

    app(SyncSourceComponent::class)->setScore($component, $classroom->participants->first()->id, 85.0);
    expect($component->scores()->first()->score)->toBe(85.0);
});

test('pengajar mengisi skor lewat tabel (autosave per sel)', function () {
    [$classroom, $session] = assessedSession(1);
    $participant = $classroom->participants->first();
    $component = SessionAssessmentComponent::factory()->for($session, 'session')->create(['max_score' => 100]);

    Livewire::actingAs($classroom->teacher)->test('session.assessment-panel', ['session' => $session])
        ->call('saveScore', $component->id, $participant->id, '88')
        ->assertReturned(['ok' => true, 'final' => 88.0])
        ->call('saveScore', $component->id, $participant->id, '150')
        ->assertReturned(fn ($result) => $result['ok'] === false);

    expect(SessionScore::first()->score)->toBe(88.0);
});

test('pengajar lain tidak bisa mengisi skor', function () {
    [$classroom, $session] = assessedSession(1);
    $component = SessionAssessmentComponent::factory()->for($session, 'session')->create();

    Livewire::actingAs(teacher())->test('session.assessment-panel', ['session' => $session])
        ->call('saveScore', $component->id, $classroom->participants->first()->id, '80')
        ->assertReturned(['ok' => false, 'message' => 'Tidak berwenang.']);
});

test('template komponen bisa disimpan lalu dipakai di sesi lain', function () {
    [$classroom, $session] = assessedSession(1);
    $teacher = $classroom->teacher;

    Livewire::actingAs($teacher)->test('session.assessment-panel', ['session' => $session])
        ->call('createComponent')
        ->set('component.name', 'Role Play')
        ->set('component.aspect', 'speaking')
        ->set('component.weight', 40)
        ->set('saveAsTemplate', true)
        ->call('saveComponent')
        ->assertHasNoErrors();

    $other = LearningSession::factory()->for($classroom)->create(['teacher_id' => $teacher->id]);
    $count = app(ManageComponents::class)->applyTemplates($other, $teacher, $teacher->hasMany(AssessmentComponentTemplate::class, 'teacher_id')->pluck('id')->all());

    expect($count)->toBe(1)->and($other->components()->first()->name)->toBe('Role Play');
});

test('pengajar menulis catatan yang boleh dilihat wali', function () {
    [$classroom, $session] = assessedSession(1);
    $participant = $classroom->participants->first();

    Livewire::actingAs($classroom->teacher)->test('session.notes-panel', ['session' => $session])
        ->call('create', $participant->id)
        ->set('form.note', 'Berani bertanya di kelas, pertahankan!')
        ->set('form.visible_to_guardian', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($session->notes()->first())
        ->user_id->toBe($participant->id)
        ->visible_to_guardian->toBeTrue();
});
