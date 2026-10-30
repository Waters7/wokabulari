<?php

use App\Actions\Assessments\SaveAssessment;
use App\Actions\Assessments\SaveSessionScore;
use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use App\Models\Assessment;
use App\Models\AssessmentAspect;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\SessionAssessmentComponent;
use App\Models\SessionNote;
use App\Notifications\AssessmentPublishedNotification;
use App\Services\AssessmentSuggester;
use App\Services\GradeService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Id aspek bawaan berdasarkan kode. */
function aspectId(string $code): int
{
    return AssessmentAspect::query()->where('code', $code)->value('id');
}

test('predikat mengikuti batas pengaturan', function () {
    $grades = app(GradeService::class);

    expect($grades->grade(85))->toBe('A')
        ->and($grades->grade(84.99))->toBe('B')
        ->and($grades->grade(75))->toBe('B')
        ->and($grades->grade(65))->toBe('C')
        ->and($grades->grade(64))->toBe('D')
        ->and($grades->grade(null))->toBeNull();
});

test('saran nilai periodik dari rata-rata komponen sesi per aspek', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $monday = now()->startOfWeek();

    foreach ([[$monday, 80], [$monday->addDays(2), 60]] as [$date, $score]) {
        $session = LearningSession::factory()->for($classroom)->create(['date' => $date]);
        $component = SessionAssessmentComponent::factory()->for($session, 'session')->create(['aspect' => LanguageAspect::Speaking, 'max_score' => 100]);
        app(SaveSessionScore::class)->handle($component, $participant, (string) $score);
    }

    $aspects = AssessmentAspect::query()->whereNotNull('code')->get();
    $suggestion = app(AssessmentSuggester::class)->suggest($classroom, $participant->id, $monday, $monday->endOfWeek(), $aspects);

    expect($suggestion[aspectId('speaking')])->toBe(70.0)
        ->and($suggestion[aspectId('listening')])->toBeNull();
});

test('simpan draf menghitung rata-rata & predikat, lalu terbit mengirim notifikasi', function () {
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();

    $assessment = app(SaveAssessment::class)->save($classroom, $participant, $classroom->teacher, PeriodType::Weekly, now(), [
        'scores' => [
            aspectId('listening') => 80, aspectId('speaking') => 90, aspectId('reading') => '', aspectId('writing') => 70,
            aspectId('vocabulary') => 85, aspectId('grammar') => '75', aspectId('attitude') => 95,
        ],
        'feedback' => 'Mantap!',
    ]);

    expect($assessment->average)->toBe(82.5)
        ->and($assessment->grade)->toBe('B')
        ->and($assessment->status)->toBe(PublishStatus::Draft)
        ->and($assessment->period_start->isMonday())->toBeTrue();

    // Peserta belum bisa melihat draf
    expect($participant->can('view', $assessment))->toBeFalse();

    app(SaveAssessment::class)->publish([$assessment]);

    expect($assessment->fresh()->isPublished())->toBeTrue()
        ->and($participant->can('view', $assessment->fresh()))->toBeTrue();
    Notification::assertSentTo($participant, AssessmentPublishedNotification::class);
});

test('simpan ulang periode yang sama memperbarui baris yang sama', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $save = app(SaveAssessment::class);

    $save->save($classroom, $participant, $classroom->teacher, PeriodType::Monthly, now()->startOfMonth()->addDays(3), ['scores' => [aspectId('speaking') => 70]]);
    $save->save($classroom, $participant, $classroom->teacher, PeriodType::Monthly, now()->startOfMonth()->addDays(10), ['scores' => [aspectId('speaking') => 90]]);

    expect(Assessment::count())->toBe(1)->and(Assessment::first()->scoreFor('speaking'))->toBe(90.0);
});

test('nilai di luar 0–100 ditolak', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();

    app(SaveAssessment::class)->save($classroom, $classroom->participants->first(), $classroom->teacher, PeriodType::Weekly, now(), ['scores' => [aspectId('speaking') => 120]]);
})->throws(InvalidArgumentException::class);

test('halaman penilaian periodik: isi saran, simpan, terbitkan', function () {
    Notification::fake();
    $classroom = Classroom::factory()->withParticipants(2)->create();
    [$a, $b] = $classroom->participants()->orderBy('users.id')->get()->all();

    Livewire::actingAs($classroom->teacher)->test('pages::teach.assessments')
        ->set('classroom', $classroom->id)
        ->set("rows.{$a->id}.scores.".aspectId('speaking'), '88')
        ->set("rows.{$b->id}.scores.".aspectId('speaking'), '72')
        ->call('publish')
        ->assertHasNoErrors();

    expect(Assessment::published()->count())->toBe(2);
});

test('pengajar lain tidak bisa membuka penilaian kelas ini', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();

    Livewire::actingAs(teacher())->test('pages::teach.assessments')
        ->set('classroom', $classroom->id)
        ->assertForbidden();
});

test('halaman nilai peserta hanya menampilkan penilaian terbit dan menandai catatan dibaca', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    Assessment::factory()->for($classroom)->for($participant)->create(['feedback' => 'DRAF-RAHASIA']);
    Assessment::factory()->for($classroom)->for($participant)->published()->create(['period_start' => now()->subWeek()->startOfWeek(), 'feedback' => 'Sudah terbit']);
    SessionNote::factory()->for($participant)->create(['note' => 'Catatan pengajar']);

    $this->actingAs($participant)->get(route('grades.index'))
        ->assertOk()
        ->assertSee('Sudah terbit')
        ->assertSee('Catatan pengajar')
        ->assertDontSee('DRAF-RAHASIA');

    Livewire::actingAs($participant)->test('pages::participant.grades')->call('markRead');
    expect(SessionNote::whereNull('read_at')->count())->toBe(0);
});
