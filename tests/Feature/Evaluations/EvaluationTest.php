<?php

use App\Actions\Evaluations\PublishEvaluation;
use App\Actions\Evaluations\SubmitEvaluation;
use App\Enums\EvaluationAudience;
use App\Enums\PointEvent;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationResponse;
use App\Models\PointTransaction;
use App\Models\ProgramEvaluation;
use App\Notifications\EvaluationAssignedNotification;
use App\Services\Evaluations\EvaluationResults;
use App\Support\Navigation;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/** Kelas dengan 2 peserta di angkatan aktif; pengajar = pengajar utama kelas. */
function surveyClassroom(): Classroom
{
    return Classroom::factory()->withParticipants(2)->create(['batch_id' => Batch::factory()->create(['is_active' => true])->id]);
}

/** Survei terbit untuk peserta & pengajar angkatan kelas, dengan pertanyaan per kelompok. */
function openSurvey(Classroom $classroom, array $attributes = []): ProgramEvaluation
{
    $evaluation = ProgramEvaluation::factory()->published()->forBatch($classroom->batch)->create(['target_teachers' => true] + $attributes);
    EvaluationQuestion::factory()->for($evaluation, 'evaluation')->create(['aspect' => 'Ruang kelas', 'sort_order' => 1]);
    EvaluationQuestion::factory()->forParticipants()->for($evaluation, 'evaluation')->create(['aspect' => 'Asrama', 'body' => 'Kebersihan kamar', 'sort_order' => 2]);
    EvaluationQuestion::factory()->text()->for($evaluation, 'evaluation')->create(['aspect' => 'Saran', 'sort_order' => 3, 'is_required' => false]);

    return $evaluation->fresh('questions');
}

test('tim kerja membuat survei sarana prasarana dari templat lalu menerbitkannya ke peserta dan pengajar', function () {
    Notification::fake();
    $classroom = surveyClassroom();

    Livewire::actingAs(programAdmin())->test('pages::manage.evaluation-form')
        ->assertSet('form.category', 'facility')
        ->assertCount('questions', 12)
        ->set('form.batch_ids', [(string) $classroom->batch_id])
        ->set('form.closes_at', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    $evaluation = ProgramEvaluation::query()->sole();
    expect($evaluation->isOpen())->toBeTrue()
        ->and($evaluation->questions)->toHaveCount(12)
        ->and($evaluation->questions->where('audience', EvaluationAudience::Participant))->not->toBeEmpty()
        ->and($evaluation->notified_at)->not->toBeNull();

    Notification::assertSentTo($classroom->participants, EvaluationAssignedNotification::class);
    Notification::assertSentTo($classroom->teacher, EvaluationAssignedNotification::class);
});

test('validasi survei: angkatan wajib, responden wajib, pilihan ganda minimal dua', function () {
    Livewire::actingAs(programAdmin())->test('pages::manage.evaluation-form')
        ->set('form.batch_ids', [])
        ->call('save')
        ->assertHasErrors('form.batch_ids');

    $batch = Batch::factory()->create();
    Livewire::actingAs(programAdmin())->test('pages::manage.evaluation-form')
        ->set('form.batch_ids', [(string) $batch->id])
        ->set('form.target_participants', false)
        ->set('form.target_teachers', false)
        ->call('save')
        ->assertHasErrors('form.closes_at');

    Livewire::actingAs(programAdmin())->test('pages::manage.evaluation-form')
        ->set('form.batch_ids', [(string) $batch->id])
        ->set('questions', [['aspect' => '', 'type' => 'choice', 'body' => 'Pilih satu', 'options' => 'Hanya satu', 'audience' => 'all', 'is_required' => true]])
        ->call('save')
        ->assertHasErrors('form.closes_at');

    expect(ProgramEvaluation::query()->count())->toBe(0);
});

test('peserta mengisi survei anonim sekali, dapat poin, dan pertanyaan khusus pengajar tidak tampil', function () {
    $this->seed(GamificationSeeder::class);
    $classroom = surveyClassroom();
    $participant = $classroom->participants->first();
    $evaluation = openSurvey($classroom);
    EvaluationQuestion::factory()->for($evaluation, 'evaluation')->create(['audience' => EvaluationAudience::Teacher, 'body' => 'Ruang kerja pengajar', 'sort_order' => 4]);
    [$kelas, $asrama] = $evaluation->questions->take(2)->all();

    $this->actingAs($participant)->get(route('dashboard'))->assertSee('data-test="survey-reminder"', false);
    $this->actingAs($participant)->get(route('evaluations.fill', $evaluation))->assertSee('Kebersihan kamar')->assertDontSee('Ruang kerja pengajar');

    Livewire::actingAs($participant)->test('pages::evaluations.fill', ['evaluation' => $evaluation])
        ->set("answers.{$kelas->id}", '4')
        ->call('submit')
        ->assertHasErrors("answers.{$asrama->id}")
        ->set("answers.{$asrama->id}", '2')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $response = EvaluationResponse::query()->sole();
    expect($response->user_id)->toBeNull()
        ->and($response->respondent_role)->toBe('participant')
        ->and($response->batch_id)->toBe($classroom->batch_id)
        ->and($response->answers()->pluck('score', 'evaluation_question_id')->all())->toEqual([$kelas->id => 4, $asrama->id => 2])
        ->and(PointTransaction::query()->where('user_id', $participant->id)->where('event_key', PointEvent::ProgramEvaluation->value)->sum('points'))->toEqual(20);

    expect(fn () => app(SubmitEvaluation::class)->handle($evaluation, $participant, [$kelas->id => 5, $asrama->id => 5]))
        ->toThrow(InvalidArgumentException::class, 'so isi survei ini');
    $this->actingAs($participant)->get(route('dashboard'))->assertDontSee('data-test="survey-reminder"', false);
});

test('pengajar angkatan ikut mengisi tanpa pertanyaan khusus peserta', function () {
    $classroom = surveyClassroom();
    $evaluation = openSurvey($classroom, ['is_anonymous' => false]);
    $teacher = $classroom->teacher;

    expect($evaluation->roleFor($teacher))->toBe('teacher')
        ->and($evaluation->questionsFor('teacher')->pluck('body'))->not->toContain('Kebersihan kamar');

    app(SubmitEvaluation::class)->handle($evaluation, $teacher, [$evaluation->questions->first()->id => 5]);

    // Survei tidak anonim menyimpan identitas responden.
    expect(EvaluationResponse::query()->sole()->user_id)->toBe($teacher->id);
});

test('survei hanya untuk angkatan sasaran, terbit, dan belum lewat tenggat', function () {
    $classroom = surveyClassroom();
    $evaluation = openSurvey($classroom);
    $outsider = participant();

    expect(ProgramEvaluation::query()->for($outsider)->exists())->toBeFalse();
    $this->actingAs($outsider)->get(route('evaluations.fill', $evaluation))->assertNotFound();

    $draft = ProgramEvaluation::factory()->forBatch($classroom->batch)->create();
    $this->actingAs($classroom->participants->first())->get(route('evaluations.fill', $draft))->assertNotFound();

    $evaluation->update(['closes_at' => now()->subMinute()]);
    expect(fn () => app(SubmitEvaluation::class)->handle($evaluation, $classroom->participants->first(), []))
        ->toThrow(InvalidArgumentException::class, 'sudah ditutup');

    // Admin tidak termasuk responden walau super admin.
    expect(superAdmin()->can('respondSurvey', openSurvey($classroom)))->toBeFalse();
});

test('hasil survei menghitung indeks kepuasan, aspek, dan partisipasi; pimpinan bisa melihat', function () {
    $classroom = surveyClassroom();
    $evaluation = openSurvey($classroom);
    [$first, $second] = $classroom->participants->all();
    [$kelas, $asrama, $saran] = $evaluation->questions->all();

    app(SubmitEvaluation::class)->handle($evaluation, $first, [$kelas->id => 5, $asrama->id => 3, $saran->id => 'Tambah kipas angin']);
    app(SubmitEvaluation::class)->handle($evaluation, $second, [$kelas->id => 3, $asrama->id => 1]);

    $summary = app(EvaluationResults::class)->summary($evaluation->fresh());

    // Rata-rata seluruh skala (5,3,3,1) = 3 → indeks 50.
    expect($summary['index'])->toBe(50.0)
        ->and($summary['responded'])->toBe(2)
        ->and($summary['target'])->toBe(3)
        ->and($summary['roles']['participant'])->toBe(['target' => 2, 'responded' => 2])
        ->and($summary['aspects'][0]['aspect'])->toBe('Asrama')
        ->and($summary['questions'][2]['texts'][0])->toBe(['text' => 'Tambah kipas angin', 'author' => null]);

    $this->actingAs(leader())->get(route('manage.evaluations.show', $evaluation))->assertOk()->assertSee('Tambah kipas angin');
    $this->actingAs(leader())->get(route('manage.evaluations.edit', $evaluation))->assertForbidden();
    $this->actingAs(teacher())->get(route('manage.evaluations.show', $evaluation))->assertForbidden();
});

test('setelah ada jawaban sasaran dan pertanyaan terkunci, tenggat masih bisa diperpanjang, dan survei tidak bisa dihapus', function () {
    $classroom = surveyClassroom();
    $evaluation = openSurvey($classroom);
    app(SubmitEvaluation::class)->handle($evaluation, $classroom->participants->first(), [
        $evaluation->questions[0]->id => 4, $evaluation->questions[1]->id => 4,
    ]);
    $newDeadline = now()->addDays(10)->setSecond(0);

    Livewire::actingAs(programAdmin())->test('pages::manage.evaluation-form', ['evaluation' => $evaluation])
        ->assertSet('locked', true)
        ->set('questions', [])
        ->set('form.closes_at', $newDeadline->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect($evaluation->fresh()->questions)->toHaveCount(3)
        ->and($evaluation->fresh()->closes_at->format('Y-m-d H:i'))->toBe($newDeadline->format('Y-m-d H:i'));

    Livewire::actingAs(superAdmin())->test('pages::manage.evaluations')->call('delete', $evaluation->id);
    expect($evaluation->fresh())->not->toBeNull();
});

test('survei terjadwal diberi tahu saat dibuka dan pengingat dikirim 24 jam sebelum tenggat', function () {
    Notification::fake();
    $classroom = surveyClassroom();
    $evaluation = openSurvey($classroom, ['opens_at' => now()->addHour(), 'closes_at' => now()->addHours(20)]);

    expect(app(PublishEvaluation::class)->publish($evaluation, programAdmin()))->toBe(0);
    Notification::assertNothingSent();

    $this->travel(2)->hours();
    $this->artisan('app:evaluation-notifications')->assertSuccessful();
    Notification::assertSentTo($classroom->participants->first(), EvaluationAssignedNotification::class, fn ($n) => ! $n->reminder);

    // Baru dibuka: belum ada pengingat walau tenggat kurang dari 24 jam.
    Notification::assertNotSentTo($classroom->participants->last(), EvaluationAssignedNotification::class, fn ($n) => $n->reminder);

    app(SubmitEvaluation::class)->handle($evaluation->fresh(), $classroom->participants->first(), [
        $evaluation->questions[0]->id => 4, $evaluation->questions[1]->id => 4,
    ]);
    $this->travel(7)->hours();
    $this->artisan('app:evaluation-notifications')->assertSuccessful();

    Notification::assertSentTo($classroom->participants->last(), EvaluationAssignedNotification::class, fn ($n) => $n->reminder);
    Notification::assertNotSentTo($classroom->participants->first(), EvaluationAssignedNotification::class, fn ($n) => $n->reminder);
});

test('menu survei tampil untuk peserta & pengajar, kelola survei untuk tim kerja dan pimpinan', function () {
    $labels = fn ($user) => collect(app(Navigation::class)->sidebar($user))->flatMap(fn ($g) => $g['items'])->pluck('label');

    expect($labels(participant()))->toContain('Survei')
        ->and($labels(teacher()))->toContain('Survei')
        ->and($labels(programAdmin()))->toContain('Survei & Evaluasi')
        ->and($labels(leader()))->toContain('Survei & Evaluasi')
        ->and($labels(guardian()))->not->toContain('Survei');

    $this->actingAs(programAdmin())->get(route('manage.evaluations.index'))->assertOk();
    $this->actingAs(participant())->get(route('evaluations.index'))->assertOk();
});
