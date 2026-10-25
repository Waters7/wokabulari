<?php

use App\Actions\Quizzes\GradeQuizAnswer;
use App\Actions\Quizzes\ImportQuestions;
use App\Actions\Quizzes\ManageQuiz;
use App\Actions\Quizzes\QuizAttemptService;
use App\Actions\Quizzes\SaveQuestion;
use App\Enums\AttemptStatus;
use App\Enums\ComponentSource;
use App\Enums\QuizType;
use App\Events\QuizCompleted;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\LearningSession;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SessionAssessmentComponent;
use App\Services\QuizAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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
