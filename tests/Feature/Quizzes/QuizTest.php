<?php

use App\Actions\Quizzes\GradeQuizAnswer;
use App\Actions\Quizzes\ImportQuestions;
use App\Actions\Quizzes\ManageQuiz;
use App\Actions\Quizzes\QuizAttemptService;
use App\Actions\Quizzes\SaveQuestion;
use App\Enums\AttemptStatus;
use App\Enums\ComponentSource;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Events\QuizCompleted;
use App\Exports\ReportExport;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\LearningSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\SessionAssessmentComponent;
use App\Notifications\QuizRetakeNotification;
use App\Services\EssayKeywordScorer;
use App\Services\QuizAnalytics;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    Storage::fake('private');
});

/**
 * Quiz dengan satu soal tiap tipe objektif + esai. Total poin = 7 (pilihan ganda 1, b/s 1, isian 1, jodoh 2, nada 1, esai 1).
 *
 * @return array{0: Classroom, 1: Quiz, 2: array<string, Question>}
 */
function quizWithAllTypes(array $quizState = []): array
{
    $classroom = Classroom::factory()->withParticipants(3)->create(['language_id' => Language::factory()->mandarin()]);
    $bank = QuestionBank::factory()->create(['language_id' => $classroom->language_id]);
    $save = app(SaveQuestion::class);

    $q = [
        'mc' => $save->handle($bank, ['type' => 'multiple_choice', 'body' => '你好 artinya?', 'reading' => 'nǐ hǎo', 'points' => 1, 'options' => [
            ['body' => 'Halo', 'is_correct' => true], ['body' => 'Terima kasih'], ['body' => 'Selamat tinggal'],
        ]]),
        'tf' => $save->handle($bank, ['type' => 'true_false', 'body' => '医生 artinya dokter.', 'points' => 1, 'correct_bool' => '1']),
        'short' => $save->handle($bank, ['type' => 'short_answer', 'body' => 'Bahasa Mandarin "terima kasih"?', 'points' => 1, 'accepted_answers' => ['谢谢', 'xièxie']]),
        'match' => $save->handle($bank, ['type' => 'matching', 'body' => 'Jodohkan.', 'points' => 2, 'options' => [
            ['body' => '医生', 'match_key' => 'dokter'], ['body' => '护士', 'match_key' => 'perawat'],
        ]]),
        'tone' => $save->handle($bank, ['type' => 'tone', 'body' => '妈', 'reading' => 'ma', 'points' => 1, 'correct_tone' => 1]),
        'essay' => $save->handle($bank, ['type' => 'essay', 'body' => 'Perkenalkan dirimu.', 'points' => 1]),
    ];

    $quiz = Quiz::factory()->for($classroom)->create($quizState);
    app(ManageQuiz::class)->addQuestions($quiz, collect($q)->pluck('id')->all());

    return [$classroom, $quiz->fresh(), $q];
}

test('penilaian otomatis tiap tipe soal; esai masuk antrean perlu dinilai', function () {
    Event::fake([QuizCompleted::class]);
    [$classroom, $quiz, $q] = quizWithAllTypes();
    $participant = $classroom->participants->first();
    $service = app(QuizAttemptService::class);

    $attempt = $service->start($quiz, $participant, 'sesi-1');
    $service->saveAnswers($attempt, [
        $q['mc']->id => ['option' => $q['mc']->options->firstWhere('is_correct', true)->id],
        $q['tf']->id => ['option' => $q['tf']->options->firstWhere('body', 'Salah')->id], // salah
        $q['short']->id => ['text' => '  XIÈXIE。 '],                                        // benar (normalisasi)
        $q['match']->id => ['pairs' => [$q['match']->options[0]->id => 'dokter', $q['match']->options[1]->id => 'pasien']], // 1 dari 2
        $q['tone']->id => ['option' => $q['tone']->options->firstWhere('body', '1')->id],
        $q['essay']->id => ['text' => 'Nama saya Angel.'],
    ], 'sesi-1');

    $attempt = $service->submit($attempt);
    $answers = $attempt->answers()->get()->keyBy('question_id');

    expect($answers[$q['mc']->id]->score)->toBe(1.0)
        ->and($answers[$q['tf']->id]->score)->toBe(0.0)
        ->and($answers[$q['short']->id]->is_correct)->toBeTrue()
        ->and($answers[$q['match']->id]->score)->toBe(1.0)
        ->and($answers[$q['match']->id]->is_correct)->toBeFalse()
        ->and($answers[$q['tone']->id]->is_correct)->toBeTrue()
        ->and($answers[$q['essay']->id]->score)->toBeNull()
        ->and($attempt->status)->toBe(AttemptStatus::NeedsGrading);
    Event::assertNotDispatched(QuizCompleted::class);

    app(GradeQuizAnswer::class)->handle($answers[$q['essay']->id], $classroom->teacher, 1, 'Bagus');

    $attempt->refresh();
    // 1 + 0 + 1 + 1 + 1 + 1 = 5 dari 7
    expect($attempt->status)->toBe(AttemptStatus::Graded)
        ->and($attempt->score)->toBe(5.0)
        ->and($attempt->percentage)->toBe(71.43);
    Event::assertDispatched(QuizCompleted::class);
});

test('kunci jawaban tidak pernah bocor ke browser sebelum dikumpulkan', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes();
    $participant = $classroom->participants->first();

    $component = Livewire::actingAs($participant)->test('pages::participant.quiz-take', ['quiz' => $quiz]);
    $html = $component->html();
    $snapshot = json_encode($component->snapshot);

    foreach ([$html, $snapshot] as $payload) {
        expect($payload)->not->toContain('is_correct')
            ->not->toContain('match_key')
            ->not->toContain('xièxie')      // jawaban isian yang diterima
            ->not->toContain('谢谢');
    }

    // Opsi pilihan tetap tampil (tanpa penanda benar)
    expect($html)->toContain('Halo')->toContain('Terima kasih');
});

test('auto-submit saat waktu habis oleh scheduler', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes(['duration_minutes' => 10]);
    $participant = $classroom->participants->first();
    $service = app(QuizAttemptService::class);

    $attempt = $service->start($quiz, $participant, 'sesi-1');
    $service->saveAnswers($attempt, [$q['mc']->id => ['option' => $q['mc']->options->firstWhere('is_correct', true)->id]], 'sesi-1');

    Carbon::setTestNow(now()->addMinutes(11));
    $this->artisan('app:submit-expired-attempts')->assertSuccessful();

    $attempt->refresh();
    expect($attempt->auto_submitted)->toBeTrue()
        ->and($attempt->status)->not->toBe(AttemptStatus::InProgress)
        ->and($attempt->answers()->where('question_id', $q['mc']->id)->value('score'))->toBe(1.0);

    expect(fn () => $service->saveAnswers($attempt, [], 'sesi-1'))->toThrow(InvalidArgumentException::class);
    Carbon::setTestNow();
});

test('jawaban ditolak setelah waktu habis walau browser masih mengirim', function () {
    [$classroom, $quiz] = quizWithAllTypes(['duration_minutes' => 5]);
    $attempt = app(QuizAttemptService::class)->start($quiz, $classroom->participants->first(), 'sesi-1');

    Carbon::setTestNow(now()->addMinutes(6));
    app(QuizAttemptService::class)->saveAnswers($attempt, [], 'sesi-1');
})->throws(InvalidArgumentException::class, 'Waktu pengerjaan sudah habis');

test('satu attempt aktif & satu sesi: membuka di sesi lain mengambil alih', function () {
    [$classroom, $quiz] = quizWithAllTypes(['max_attempts' => 2]);
    $participant = $classroom->participants->first();
    $service = app(QuizAttemptService::class);

    $first = $service->start($quiz, $participant, 'hp-lama');
    $again = $service->start($quiz, $participant, 'hp-baru');

    expect($again->id)->toBe($first->id)
        ->and(QuizAttempt::count())->toBe(1);

    expect(fn () => $service->saveAnswers($first->fresh(), [], 'hp-lama'))
        ->toThrow(InvalidArgumentException::class, 'perangkat atau sesi lain');
});

test('jumlah percobaan dibatasi dan quiz di luar jadwal ditolak', function () {
    [$classroom, $quiz] = quizWithAllTypes(['max_attempts' => 1]);
    $participant = $classroom->participants->first();
    $service = app(QuizAttemptService::class);

    $service->submit($service->start($quiz, $participant, 's'));
    expect(fn () => $service->start($quiz, $participant, 's'))->toThrow(InvalidArgumentException::class, 'habis');

    $quiz->update(['opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
    expect(fn () => $service->start($quiz, $classroom->participants->last(), 's'))->toThrow(InvalidArgumentException::class, 'dibuka');
});

test('N-Gain pretest–posttest per peserta dan kategori', function () {
    expect(QuizAnalytics::gain(40, 82))->toBe(0.7)
        ->and(QuizAnalytics::category(0.7))->toBe('Tinggi')
        ->and(QuizAnalytics::category(0.45))->toBe('Sedang')
        ->and(QuizAnalytics::category(0.1))->toBe('Rendah')
        ->and(QuizAnalytics::gain(100, 100))->toBeNull();

    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    foreach ([[QuizType::Pretest, 50.0], [QuizType::Posttest, 80.0]] as [$type, $percentage]) {
        $quiz = Quiz::factory()->for($classroom)->type($type)->create();
        $quiz->attempts()->create(['user_id' => $participant->id, 'started_at' => now(), 'expires_at' => now(), 'submitted_at' => now(), 'status' => AttemptStatus::Graded, 'percentage' => $percentage, 'score' => $percentage, 'max_score' => 100]);
    }

    $result = app(QuizAnalytics::class)->nGain($classroom);

    expect($result['rows']->first()['gain'])->toBe(0.6)
        ->and($result['rows']->first()['category'])->toBe('Sedang')
        ->and($result['average'])->toBe(0.6);
});

test('analisis butir menghitung tingkat kesukaran', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes();
    $service = app(QuizAttemptService::class);

    foreach ($classroom->participants as $i => $participant) {
        $attempt = $service->start($quiz, $participant, 's'.$i);
        $correct = $i < 2 ? $q['mc']->options->firstWhere('is_correct', true)->id : $q['mc']->options->firstWhere('is_correct', false)->id;
        $service->saveAnswers($attempt, [$q['mc']->id => ['option' => $correct]], 's'.$i);
        $service->submit($attempt->fresh());
    }

    $item = app(QuizAnalytics::class)->itemAnalysis($quiz)->firstWhere(fn ($i) => $i['question']->id === $q['mc']->id);
    expect($item['difficulty'])->toBe(0.67)->and($item['difficulty_label'])->toBe('Sedang');
});

test('nilai quiz yang ditautkan ke sesi masuk komponen sesi', function () {
    $session = null;
    [$classroom, $quiz, $q] = quizWithAllTypes();
    $session = LearningSession::factory()->for($classroom)->create();
    $quiz->update(['learning_session_id' => $session->id]);
    $quiz->questions()->detach($q['essay']->id);
    $participant = $classroom->participants->first();

    $service = app(QuizAttemptService::class);
    $attempt = $service->start($quiz->fresh(), $participant, 's');
    $service->saveAnswers($attempt, [$q['mc']->id => ['option' => $q['mc']->options->firstWhere('is_correct', true)->id]], 's');
    $service->submit($attempt->fresh());

    $component = SessionAssessmentComponent::where('source', ComponentSource::Quiz)->where('source_id', $quiz->id)->firstOrFail();
    expect($component->scores()->where('user_id', $participant->id)->value('score'))->toBe(round(1 / 6 * 100, 2));
});

test('import soal dari Excel (baris tabel)', function () {
    $bank = QuestionBank::factory()->create();

    $result = app(ImportQuestions::class)->handle($bank, [
        ['tipe' => 'pg', 'soal' => 'Nurse artinya', 'opsi_a' => 'Perawat', 'opsi_b' => 'Dokter', 'jawaban' => 'a', 'poin' => 2],
        ['tipe' => 'bs', 'soal' => 'Doctor = dokter', 'jawaban' => 'benar'],
        ['tipe' => 'nada', 'soal' => '马', 'bacaan' => 'ma', 'jawaban' => '3'],
        ['tipe' => 'jodoh', 'soal' => 'Jodohkan', 'jawaban' => 'a=1|b=2'],
        ['tipe' => 'aneh', 'soal' => 'Tidak valid'],
        ['tipe' => 'pg', 'soal' => 'Tanpa jawaban', 'opsi_a' => 'x', 'opsi_b' => 'y', 'jawaban' => ''],
    ]);

    expect($result['created'])->toBe(4)
        ->and($result['errors'])->toHaveCount(2)
        ->and($bank->questions()->count())->toBe(4);
});

test('halaman quiz tampil sesuai role dan alur kerjakan → kumpul → hasil', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes();
    $quiz->questions()->detach($q['essay']->id);
    $participant = $classroom->participants->first();

    foreach (['teach.questions.index', 'teach.quizzes.index', 'teach.grading'] as $route) {
        $this->actingAs($classroom->teacher)->get(route($route))->assertOk();
        $this->actingAs($participant)->get(route($route))->assertForbidden();
    }
    $this->actingAs($classroom->teacher)->get(route('teach.quizzes.show', $quiz))->assertOk();
    $this->actingAs($participant)->get(route('quizzes.index'))->assertOk()->assertSee($quiz->title);

    Livewire::actingAs($participant)->test('pages::participant.quiz-take', ['quiz' => $quiz])
        ->set("answers.{$q['mc']->id}", (string) $q['mc']->options->firstWhere('is_correct', true)->id)
        ->call('submit')
        ->assertRedirect();

    $attempt = QuizAttempt::firstOrFail();
    expect($attempt->status)->toBe(AttemptStatus::Graded);
    $this->actingAs($participant)->get(route('quizzes.result', $attempt))->assertOk()->assertSee('Halo');
    $this->actingAs($classroom->participants->last())->get(route('quizzes.result', $attempt))->assertForbidden();
});

test('gambar soal bisa ditambahkan ke pilihan ganda & esai; tampil di bank soal, saat dikerjakan, dan saat dinilai', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create(['language_id' => Language::factory()->mandarin()]);
    $teacher = $classroom->teacher;
    $bank = QuestionBank::factory()->create(['language_id' => $classroom->language_id, 'teacher_id' => $teacher->id]);

    Livewire::actingAs($teacher)->test('pages::teach.question-bank', ['bank' => $bank])
        ->call('create')
        ->set('form.body', '这是什么？(Zhè shì shénme?)')
        ->set('form.options.0.body', '体温计')
        ->set('form.options.1.body', '血压计')
        ->set('form.correct_index', 1)
        ->set('media', UploadedFile::fake()->image('soal-31.jpg', 400, 300))
        ->call('save')
        ->assertHasNoErrors()
        ->call('create')
        ->set('form.type', 'essay')
        ->set('form.body', 'Deskripsikan gambar berikut minimal 3 kalimat.')
        ->set('form.explanation', '她们在公园散步。')
        ->set('media', UploadedFile::fake()->image('deskripsi.jpg', 400, 300))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Gambar soal (opsional)')
        ->assertSeeHtml('alt="Gambar soal"');

    [$choice, $essay] = $bank->questions()->orderBy('id')->get()->all();
    expect($choice->hasImage())->toBeTrue()->and($essay->hasImage())->toBeTrue();

    $quiz = Quiz::factory()->for($classroom)->create(['teacher_id' => $teacher->id]);
    app(ManageQuiz::class)->addQuestions($quiz, [$choice->id, $essay->id]);
    $participant = $classroom->participants->first();

    Livewire::actingAs($participant)->test('pages::participant.quiz-take', ['quiz' => $quiz])->assertSeeHtml('<img src=');

    $attempt = app(QuizAttemptService::class)->start($quiz->fresh(), $participant, 'sesi-a', '127.0.0.1', 'pest');
    app(QuizAttemptService::class)->saveAnswers($attempt, [$essay->id => ['text' => '她们在公园。']], 'sesi-a');
    app(QuizAttemptService::class)->submit($attempt->fresh());

    Livewire::actingAs($teacher)->test('pages::teach.grading')
        ->assertSee('她们在公园散步。')
        ->assertSeeHtml('alt="Gambar soal"');
});

test('pilih semua soal bank sekaligus saat menyusun quiz', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create(['language_id' => Language::factory()->mandarin()]);
    $bank = QuestionBank::factory()->create(['language_id' => $classroom->language_id]);
    $save = app(SaveQuestion::class);
    foreach (range(1, 5) as $i) {
        $save->handle($bank, ['type' => 'essay', 'body' => "Soal {$i}", 'points' => 1]);
    }
    $quiz = Quiz::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);
    app(ManageQuiz::class)->addQuestions($quiz, [$bank->questions()->value('id')]);

    Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-show', ['quiz' => $quiz])
        ->set('bank', $bank->id)
        ->assertSee('Pilih semua (4)')
        ->call('toggleAll')
        ->assertCount('selected', 4)
        ->assertSee('Batal pilih semua')
        ->call('addSelected');

    expect($quiz->fresh()->questions()->count())->toBe(5);
});

test('quiz yang belum dikerjakan bisa dihapus dari pengaturan; soalnya tetap di bank soal', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes(['status' => QuizStatus::Draft]);
    $teacher = $classroom->teacher;

    Livewire::actingAs($teacher)->test('pages::teach.quizzes')
        ->call('edit', $quiz->id)
        ->assertSeeHtml('data-test="quiz-delete"')
        ->call('delete', $quiz->id)
        ->assertSet('showForm', false);

    expect(Quiz::query()->find($quiz->id))->toBeNull()
        ->and(Quiz::withTrashed()->find($quiz->id))->not->toBeNull()
        ->and(Question::query()->whereKey(collect($q)->pluck('id'))->count())->toBe(6);

    $this->actingAs($classroom->participants->first())->get(route('quizzes.take', $quiz))->assertNotFound();
});

test('quiz yang sudah dikerjakan tidak bisa dihapus; pengajar lain tidak bisa menghapus', function () {
    [$classroom, $quiz] = quizWithAllTypes();
    $quiz->update(['status' => QuizStatus::Published]);
    app(QuizAttemptService::class)->start($quiz->fresh(), $classroom->participants->first(), 'sesi-x');

    Livewire::actingAs($classroom->teacher)->test('pages::teach.quizzes')
        ->call('edit', $quiz->id)
        ->assertDontSeeHtml('data-test="quiz-delete"')
        ->assertSee('tidak bisa dihapus')
        ->call('delete', $quiz->id);
    expect(Quiz::query()->find($quiz->id))->not->toBeNull();

    expect(fn () => app(ManageQuiz::class)->delete($quiz))->toThrow(InvalidArgumentException::class, 'sudah dikerjakan');

    $other = Quiz::factory()->create();
    Livewire::actingAs(teacher())->test('pages::teach.quizzes')->call('delete', $other->id)->assertForbidden();
});

test('hapus dari halaman detail quiz kembali ke daftar dengan pesan', function () {
    [$classroom, $quiz] = quizWithAllTypes();

    Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-show', ['quiz' => $quiz])
        ->assertSeeHtml('data-test="quiz-delete"')
        ->call('deleteQuiz')
        ->assertRedirect(route('teach.quizzes.index'));

    expect(Quiz::query()->find($quiz->id))->toBeNull();
    $this->actingAs($classroom->teacher)->withSession(['status' => 'Quiz "'.$quiz->title.'" dihapus.'])
        ->get(route('teach.quizzes.index'))->assertSee('dihapus');
});

test('atur poin otomatis: sama rata dengan jumlah pas, per jenis soal, dan kembali ke poin bank soal', function () {
    [$classroom, $quiz, $q] = quizWithAllTypes(['status' => QuizStatus::Draft]);

    // 6 soal, total 100: 16,67 ×4 + 16,66 ×2 = 100 tepat.
    $page = Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-show', ['quiz' => $quiz])
        ->assertSeeHtml('data-test="auto-points"')
        ->set('pointTotal', '100')
        ->call('applyPoints')
        ->assertHasNoErrors();

    $points = $quiz->fresh()->questions->map(fn ($question) => (float) $question->pivot->points);
    expect(round($points->sum(), 2))->toBe(100.0)
        ->and($points->unique()->sort()->values()->all())->toBe([16.66, 16.67])
        ->and($quiz->fresh()->totalPoints())->toBe(100.0);

    $page->set('pointMode', 'jenis')
        ->set('typePoints.multiple_choice', '2')
        ->set('typePoints.matching', '10')
        ->set('typePoints.essay', '')
        ->call('applyPoints')
        ->assertHasNoErrors();
    $fresh = $quiz->fresh();
    expect($fresh->pointsFor($fresh->questions->firstWhere('id', $q['mc']->id)))->toBe(2.0)
        ->and($fresh->pointsFor($fresh->questions->firstWhere('id', $q['match']->id)))->toBe(10.0)
        ->and($fresh->pointsFor($fresh->questions->firstWhere('id', $q['essay']->id)))->toBe(16.66);

    $page->set('pointMode', 'rata')->set('pointTotal', '0')->call('applyPoints')->assertHasErrors('pointTotal');

    $page->call('resetPoints');
    expect($quiz->fresh()->questions->pluck('pivot.points')->filter()->all())->toBe([])
        ->and($quiz->fresh()->totalPoints())->toBe(7.0);
});

test('poin otomatis ditolak setelah quiz dikerjakan', function () {
    [$classroom, $quiz] = quizWithAllTypes();
    $quiz->update(['status' => QuizStatus::Published]);
    app(QuizAttemptService::class)->start($quiz->fresh(), $classroom->participants->first(), 'sesi-p');

    expect(fn () => app(ManageQuiz::class)->distributePoints($quiz, 100))->toThrow(InvalidArgumentException::class);
    Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-show', ['quiz' => $quiz])->assertDontSeeHtml('data-test="auto-points"');
});

test('kumpul otomatis dari HP yang jamnya salah ditolak sebelum waktu habis; hitung mundur memakai jam server', function () {
    [$classroom, $quiz] = quizWithAllTypes(['duration_minutes' => 60]);
    $participant = $classroom->participants->first();

    $page = Livewire::actingAs($participant)->test('pages::participant.quiz-take', ['quiz' => $quiz])
        ->assertSeeHtml("'quiz-timeout', '")
        ->call('submit', true)
        ->assertDispatched('quiz-time-sync')
        ->assertNoRedirect();

    $attempt = $quiz->attempts()->where('user_id', $participant->id)->first();
    expect($attempt->status)->toBe(AttemptStatus::InProgress);

    // Setelah waktu benar-benar habis, kumpul otomatis diterima.
    $this->travel(61)->minutes();
    $page->call('submit', true)->assertRedirect(route('quizzes.result', $attempt));
    expect($attempt->fresh()->status)->not->toBe(AttemptStatus::InProgress)
        ->and($attempt->fresh()->auto_submitted)->toBeTrue();
});

test('reset pengerjaan peserta: attempt disembunyikan, peserta boleh mengerjakan ulang dan diberi tahu', function () {
    [$classroom, $quiz] = quizWithAllTypes(['max_attempts' => 1]);
    $participant = $classroom->participants->first();
    $service = app(QuizAttemptService::class);
    $attempt = $service->submit($service->start($quiz, $participant, 'sesi-r'), auto: true);

    expect(fn () => $service->start($quiz->fresh(), $participant, 'sesi-r'))->toThrow(InvalidArgumentException::class, 'habis');

    Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-show', ['quiz' => $quiz, 'tab' => 'hasil'])
        ->set('tab', 'hasil')
        ->assertSeeHtml('data-test="reset-'.$attempt->id.'"')
        ->call('resetAttempt', $attempt->id);

    expect(QuizAttempt::query()->find($attempt->id))->toBeNull()
        ->and(QuizAttempt::withTrashed()->find($attempt->id))->not->toBeNull()
        ->and(Activity::query()->where('description', 'Reset pengerjaan quiz peserta')->exists())->toBeTrue();
    Notification::assertSentTo($participant, QuizRetakeNotification::class);

    $again = $service->start($quiz->fresh(), $participant, 'sesi-r2');
    expect($again->id)->not->toBe($attempt->id)->and($again->status)->toBe(AttemptStatus::InProgress);

    Livewire::actingAs(teacher())->test('pages::teach.quiz-show', ['quiz' => $quiz])->assertForbidden();
});

test('perlu dinilai: semua jawaban esai satu peserta dalam satu kartu, simpan semua sekaligus', function () {
    $classroom = Classroom::factory()->withParticipants(2)->create(['language_id' => Language::factory()->mandarin()]);
    [$ani, $budi] = $classroom->participants()->orderBy('users.id')->get()->all();
    $ani->update(['name' => 'Ani Lumowa']);
    $budi->update(['name' => 'Budi Kalalo']);
    $bank = QuestionBank::factory()->create(['language_id' => $classroom->language_id]);
    $save = app(SaveQuestion::class);
    $e1 = $save->handle($bank, ['type' => 'essay', 'body' => 'Sebutkan 3 olahraga.', 'points' => 2, 'explanation' => '跑步, 游泳, 打篮球']);
    $e2 = $save->handle($bank, ['type' => 'essay', 'body' => 'Sebutkan 5 bagian tubuh.', 'points' => 2]);
    $quiz = Quiz::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);
    app(ManageQuiz::class)->addQuestions($quiz, [$e1->id, $e2->id]);
    $service = app(QuizAttemptService::class);
    foreach ([$ani, $budi] as $user) {
        $attempt = $service->start($quiz->fresh(), $user, 'sesi-'.$user->id);
        $service->saveAnswers($attempt, [$e1->id => ['text' => 'pǎobù, yóuyǒng'], $e2->id => ['text' => 'tóu, shǒu']], 'sesi-'.$user->id);
        $service->submit($attempt->fresh());
    }
    $aniAttempt = $quiz->attempts()->where('user_id', $ani->id)->first();
    [$a1, $a2] = $aniAttempt->answers()->orderBy('id')->get()->all();

    $page = Livewire::actingAs($classroom->teacher)->withQueryParams(['tampilan' => 'peserta'])->test('pages::teach.grading')
        ->assertSeeHtml('data-test="attempt-'.$aniAttempt->id.'"')
        ->assertSeeInOrder(['Ani Lumowa', 'Soal 1', 'Sebutkan 3 olahraga.', 'pǎobù, yóuyǒng', 'Soal 2', 'Sebutkan 5 bagian tubuh.', 'Budi Kalalo'])
        ->set('search', 'kalalo')
        ->assertDontSeeHtml('data-test="attempt-'.$aniAttempt->id.'"')
        ->set('search', '');

    // Nilai di luar batas ditandai; yang valid tetap tersimpan.
    $page->set("grades.{$a1->id}.score", '1,5')->set("grades.{$a2->id}.score", '9')->call('gradeAttempt', $aniAttempt->id)
        ->assertHasErrors("grades.{$a2->id}.score");
    expect($a1->fresh()->score)->toBe(1.5)->and($aniAttempt->fresh()->status)->toBe(AttemptStatus::NeedsGrading);

    $page->set("grades.{$a2->id}.score", '2')->set("grades.{$a2->id}.feedback", 'Bagus')->call('gradeAttempt', $aniAttempt->id)->assertHasNoErrors();
    expect($aniAttempt->fresh()->status)->toBe(AttemptStatus::Graded)
        ->and($a2->fresh()->teacher_feedback)->toBe('Bagus');
});

test('perlu dinilai per soal: semua jawaban satu soal, makro nilai massal dan kata kunci', function () {
    $classroom = Classroom::factory()->withParticipants(3)->create(['language_id' => Language::factory()->mandarin()]);
    [$ani, $budi, $citra] = $classroom->participants()->orderBy('users.id')->get()->all();
    $ani->update(['name' => 'Ani Lumowa']);
    $budi->update(['name' => 'Budi Kalalo']);
    $citra->update(['name' => 'Citra Mamuaja']);
    $bank = QuestionBank::factory()->create(['language_id' => $classroom->language_id]);
    $save = app(SaveQuestion::class);
    $sport = $save->handle($bank, ['type' => 'essay', 'body' => 'Sebutkan 2 olahraga.', 'points' => 2, 'explanation' => '跑步, 游泳']);
    $body = $save->handle($bank, ['type' => 'essay', 'body' => 'Sebutkan bagian tubuh.', 'points' => 4]);
    $quiz = Quiz::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);
    app(ManageQuiz::class)->addQuestions($quiz, [$sport->id, $body->id]);
    $service = app(QuizAttemptService::class);
    foreach ([[$ani, 'pǎobù dan 游泳'], [$budi, 'paobu saja'], [$citra, 'tidak tahu']] as [$user, $text]) {
        $attempt = $service->start($quiz->fresh(), $user, 'sesi-'.$user->id);
        $service->saveAnswers($attempt, [$sport->id => ['text' => $text], $body->id => ['text' => 'tóu']], 'sesi-'.$user->id);
        $service->submit($attempt->fresh());
    }
    $answerOf = fn ($user, $question) => QuizAnswer::query()->where('question_id', $question->id)
        ->whereHas('attempt', fn ($q) => $q->where('user_id', $user->id))->first();
    [$aAni, $aBudi, $aCitra] = [$answerOf($ani, $sport), $answerOf($budi, $sport), $answerOf($citra, $sport)];

    $page = Livewire::actingAs($classroom->teacher)->test('pages::teach.grading')
        ->assertSet('quiz', $quiz->id)
        ->assertSeeHtml('data-test="grading-macro"')
        ->assertSeeInOrder(['Sebutkan 2 olahraga.', 'Ani Lumowa', 'pǎobù dan 游泳', 'Budi Kalalo', 'Citra Mamuaja', 'tidak tahu'])
        ->assertDontSee('Sebutkan bagian tubuh.');

    // Kata kunci: Ani menyebut 2 dari 2 (penuh), Budi 1 dari 2 (setengah), Citra tidak ada.
    $page->set('keywords', '跑步/paobu, 游泳/youyong')->set('keywordsNeeded', '2')->call('applyKeywords')
        ->assertSet("grades.{$aAni->id}.score", '2')
        ->assertSet("grades.{$aBudi->id}.score", '1')
        ->assertSet("grades.{$aCitra->id}.score", '0');

    // Nilai massal untuk yang dicentang; di luar batas ditolak.
    $page->set('selected', [(string) $aBudi->id])->set('bulkScore', '5')->call('applyBulk')->assertHasErrors('bulkScore')
        ->set('bulkScore', '1,5')->call('applyBulk')->assertSet("grades.{$aBudi->id}.score", '1.5');

    $page->call('saveQuestion')->assertHasNoErrors()
        ->assertSee('Sebutkan bagian tubuh.');   // pindah otomatis ke soal berikutnya yang masih menunggu

    expect($aAni->fresh()->score)->toBe(2.0)
        ->and($aBudi->fresh()->score)->toBe(1.5)
        ->and($aCitra->fresh()->score)->toBe(0.0);
});

test('pencocokan kata kunci: pinyin tanpa tanda nada, huruf Mandarin, alternatif', function () {
    $scorer = new EssayKeywordScorer;
    $keywords = EssayKeywordScorer::parse("跑步/paobu, 游泳/yóuyǒng\n打篮球/lanqiu");

    expect($keywords)->toHaveCount(3)
        ->and($scorer->matches('Saya suka Pǎo Bù dan 游泳', $keywords))->toBe(2)
        ->and($scorer->score('lanqiu, youyong, paobu, tenis', $keywords, 3, 2))->toBe(2.0)
        ->and($scorer->score('sanbu', $keywords, 3, 2))->toBe(0.0)
        ->and($scorer->score('打篮球', $keywords, 2, 3))->toBe(1.5);
});

/** Peserta pertama mengerjakan quiz semua tipe (pilihan ganda benar, b/s salah, jodoh 1 dari 2, esai menunggu). */
function submittedQuizPaper(): array
{
    Event::fake([QuizCompleted::class]);
    [$classroom, $quiz, $q] = quizWithAllTypes(['title' => 'Pretest Mandarin']);
    $participant = $classroom->participants->first();
    $participant->update(['name' => 'Angel Rondonuwu']);
    $service = app(QuizAttemptService::class);

    $attempt = $service->start($quiz, $participant, 'sesi-1');
    $service->saveAnswers($attempt, [
        $q['mc']->id => ['option' => $q['mc']->options->firstWhere('is_correct', true)->id],
        $q['tf']->id => ['option' => $q['tf']->options->firstWhere('body', 'Salah')->id],
        $q['match']->id => ['pairs' => [$q['match']->options[0]->id => 'dokter', $q['match']->options[1]->id => 'pasien']],
        $q['essay']->id => ['text' => 'Nama saya Angel.'],
    ], 'sesi-1');
    $service->submit($attempt);

    return [$classroom, $quiz->fresh(), $q, $participant];
}

test('soal & hasil quiz diunduh Excel: kunci, hasil, nilai per soal, dan jawaban peserta', function () {
    Excel::fake();
    [$classroom, $quiz] = submittedQuizPaper();

    $this->actingAs($classroom->teacher)->get(route('teach.quizzes.excel', $quiz))->assertOk();

    Excel::assertDownloaded('soal-hasil-pretest-mandarin-'.now()->format('Y-m-d').'.xlsx', function (ReportExport $export) {
        [$soal, $hasil, $perSoal, $jawaban] = array_map(fn ($sheet) => $sheet->array(), $export->sheets());
        $keys = array_column(array_slice($soal, 6), 5, 1);
        $answers = collect(array_slice($jawaban, 6))->keyBy(2);

        return $soal[5][5] === 'Kunci jawaban'
            && $soal[6][4] === "A. Halo\nB. Terima kasih\nC. Selamat tinggal" && $soal[6][5] === 'A. Halo'
            && $keys['Benar/Salah'] === 'Benar'
            && $keys['Isian singkat'] === '谢谢 / xièxie'
            && $keys['Menjodohkan'] === '医生 → dokter; 护士 → perawat'
            && $keys['Nada Mandarin'] === 'Nada 1'
            && $hasil[6][1] === 'Angel Rondonuwu' && $hasil[6][5] === 'Perlu dinilai'
            && collect(array_slice($hasil, 7))->contains(fn ($row) => $row[5] === 'Belum mengerjakan')
            && $perSoal[5][2] === 'No 1 (maks 1)' && $perSoal[6][2] == 1
            && $answers['Pilihan ganda'][3] === 'A. Halo' && $answers['Pilihan ganda'][5] === 'Benar'
            && $answers['Benar/Salah'][5] === 'Salah'
            && $answers['Menjodohkan'][3] === '医生 → dokter; 护士 → pasien' && $answers['Menjodohkan'][5] === 'Sebagian'
            && $answers['Esai'][3] === 'Nama saya Angel.' && $answers['Esai'][5] === 'Menunggu dinilai'
            && in_array($answers['Isian singkat'][5], ['Tidak dijawab', 'Salah'], true);
    });
});

test('cetak quiz: lembar peserta tanpa kunci, lembar pengajar dengan kunci, dan hasil', function () {
    [$classroom, $quiz, $q] = submittedQuizPaper();

    Livewire::actingAs($classroom->teacher)->test('pages::teach.quiz-print', ['quiz' => $quiz])
        ->assertSee('Pretest Mandarin')
        ->assertSeeHtml('images/brand/kop-instansi.png')
        ->assertSee('Dicetak:')
        ->assertSee('你好 artinya?')
        ->assertSee('Perkenalkan dirimu.')
        ->assertDontSee('谢谢 / xièxie')
        ->assertDontSee('Angel Rondonuwu')
        ->set('withKey', true)
        ->assertSee('Kunci:')->assertSee('谢谢 / xièxie')
        ->assertSee('→ dokter')
        ->set('part', 'hasil')
        ->assertDontSee('你好 artinya?')
        ->assertSeeInOrder(['Hasil', 'Angel Rondonuwu', 'Perlu dinilai', 'Belum mengerjakan']);

    $this->actingAs($classroom->teacher)->get(route('teach.quizzes.show', $quiz))
        ->assertSee(route('teach.quizzes.print', ['quiz' => $quiz, 'bagian' => 'soal']), false)
        ->assertSee(route('teach.quizzes.excel', $quiz), false);
});

test('cetak & Excel soal quiz hanya untuk pengajar quiz itu', function () {
    [$classroom, $quiz, , $participant] = submittedQuizPaper();

    $this->actingAs(teacher())->get(route('teach.quizzes.print', $quiz))->assertForbidden();
    $this->actingAs(teacher())->get(route('teach.quizzes.excel', $quiz))->assertForbidden();
    $this->actingAs($participant)->get(route('teach.quizzes.print', $quiz))->assertForbidden();
    $this->actingAs($participant)->get(route('teach.quizzes.excel', $quiz))->assertForbidden();
});
