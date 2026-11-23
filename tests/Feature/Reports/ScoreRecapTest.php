<?php

use App\Actions\Quizzes\ManageQuiz;
use App\Actions\Quizzes\SaveQuestion;
use App\Enums\AttemptStatus;
use App\Enums\QuizType;
use App\Exports\ReportExport;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Reports\QuizCombination;
use App\Services\Reports\ReportCard;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pretest dipecah dua quiz: pilihan ganda (maks 10) + esai (maks 10).
 * Angel 8 + 9 = 17 → 85; Brian 10 + 7 (esai belum selesai dinilai) = 17 → 85; Cindy 6 + tidak mengerjakan = 6 → 30.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Makassar'));

    $this->teacher = teacher();
    $this->batch = Batch::factory()->create(['start_date' => '2026-11-02', 'end_date' => '2027-01-29', 'passing_score' => 70]);
    $this->classroom = Classroom::factory()->for($this->batch)->withParticipants(3)->create(['name' => 'Kelas Mandarin A', 'teacher_id' => $this->teacher->id]);
    [$this->a, $this->b, $this->c] = $this->classroom->participants()->orderBy('users.id')->get()->all();
    $this->a->update(['name' => 'Angel Rondonuwu']);
    $this->b->update(['name' => 'Brian Lumowa']);
    $this->c->update(['name' => 'Cindy Sondakh']);

    $bank = QuestionBank::factory()->create(['language_id' => $this->classroom->language_id]);
    $save = app(SaveQuestion::class);
    $mc = $save->handle($bank, ['type' => 'multiple_choice', 'body' => '你好 artinya?', 'points' => 10, 'options' => [['body' => 'Halo', 'is_correct' => true], ['body' => 'Dadah']]]);
    $essay = $save->handle($bank, ['type' => 'essay', 'body' => 'Perkenalkan dirimu.', 'points' => 10]);

    $this->pg = Quiz::factory()->for($this->classroom)->type(QuizType::Pretest)->create(['title' => 'Pretest PG']);
    $this->esai = Quiz::factory()->for($this->classroom)->type(QuizType::Pretest)->create(['title' => 'Pretest Esai']);
    $this->other = Quiz::factory()->for($this->classroom)->create(['title' => 'Quiz Kosakata 1']);
    app(ManageQuiz::class)->addQuestions($this->pg, [$mc->id]);
    app(ManageQuiz::class)->addQuestions($this->esai, [$essay->id]);

    $attempt = fn (Quiz $quiz, User $user, float $score, AttemptStatus $status = AttemptStatus::Graded) => QuizAttempt::query()->create([
        'quiz_id' => $quiz->id, 'user_id' => $user->id, 'started_at' => '2026-11-03 09:00', 'expires_at' => '2026-11-03 09:30',
        'submitted_at' => '2026-11-03 09:20', 'score' => $score, 'max_score' => 10,
        'percentage' => $status === AttemptStatus::NeedsGrading ? null : $score * 10, 'status' => $status,
    ]);

    $attempt($this->pg, $this->a, 8);
    $attempt($this->esai, $this->a, 9);
    $attempt($this->pg, $this->b, 10);
    $attempt($this->esai, $this->b, 7, AttemptStatus::NeedsGrading);
    $attempt($this->pg, $this->c, 6);
    // Attempt yang sudah di-reset (soft delete) dan yang masih berjalan tidak dihitung.
    $attempt($this->esai, $this->c, 10)->delete();
    $attempt($this->esai, $this->c, 0, AttemptStatus::InProgress);
});

afterEach(fn () => Carbon::setTestNow());

test('gabungan quiz menjumlahkan poin; bagian kosong bernilai 0 dan peringkat sama untuk nilai sama', function () {
    $result = app(QuizCombination::class)->combine($this->classroom, collect([$this->pg, $this->esai]));
    $rows = $result['rows']->keyBy(fn ($row) => $row['user']->id);

    expect($rows[$this->a->id])->toMatchArray(['score' => 17.0, 'max' => 20.0, 'percentage' => 85.0, 'rank' => 1, 'pending' => 0, 'missing' => 0])
        ->and($rows[$this->b->id])->toMatchArray(['score' => 17.0, 'percentage' => 85.0, 'rank' => 1, 'pending' => 1])
        ->and($rows[$this->c->id])->toMatchArray(['score' => 6.0, 'percentage' => 30.0, 'rank' => 3, 'missing' => 1])
        ->and($rows[$this->c->id]['parts'][$this->esai->id]['status'])->toBe('missing')
        ->and($rows[$this->b->id]['parts'][$this->esai->id]['status'])->toBe('pending')
        ->and($result['average'])->toBe(66.67)
        ->and($result['highest'])->toBe(85.0)
        ->and($result['lowest'])->toBe(30.0)
        ->and($result['complete'])->toBe(1);
});

test('rapor memakai gabungan poin semua pretest; esai yang belum dinilai ditandai sementara', function () {
    $cards = app(ReportCard::class);

    expect($cards->for($this->classroom, $this->a)['nGain']['row'])->toMatchArray(['pre' => 85.0, 'post' => null, 'gain' => null, 'pending' => 0])
        ->and($cards->for($this->classroom, $this->b)['nGain']['row'])->toMatchArray(['pre' => 85.0, 'pending' => 1])
        ->and($cards->for($this->classroom, $this->c)['nGain']['row']['pre'])->toBe(30.0);

    // Posttest gabungan: Angel 18/20 = 90 → N-Gain (90 − 85) ÷ (100 − 85) = 0,33 (sedang).
    $post = Quiz::factory()->for($this->classroom)->type(QuizType::Posttest)->create(['title' => 'Posttest PG']);
    app(ManageQuiz::class)->addQuestions($post, $this->pg->questions->pluck('id')->all());
    $post->questions()->updateExistingPivot($this->pg->questions->first()->id, ['points' => 20]);
    QuizAttempt::query()->create(['quiz_id' => $post->id, 'user_id' => $this->a->id, 'started_at' => '2026-11-19 09:00', 'expires_at' => '2026-11-19 09:30',
        'submitted_at' => '2026-11-19 09:20', 'score' => 18, 'max_score' => 20, 'percentage' => 90, 'status' => AttemptStatus::Graded]);

    expect($cards->for($this->classroom, $this->a)['nGain']['row'])->toMatchArray(['pre' => 85.0, 'post' => 90.0, 'gain' => 0.33, 'category' => 'Sedang']);
});

test('pengajar kelas membuka rekap gabungan: pretest terpilih otomatis dan bisa unduh Excel', function () {
    Excel::fake();

    Livewire::actingAs($this->teacher)->test('pages::teach.score-recap')
        ->assertSet('classroom', $this->classroom->id)
        ->assertSet('quizIds', [(string) $this->pg->id, (string) $this->esai->id])
        ->assertSeeInOrder(['Angel Rondonuwu', '85', 'Brian Lumowa', '85', 'Cindy Sondakh', '30'])
        ->assertSee('Quiz Kosakata 1')
        ->call('export');

    Excel::assertDownloaded('rekap-gabungan-quiz-kelas-mandarin-a-2026-11-20.xlsx', function (ReportExport $export) {
        $rows = $export->sheets()[0]->array();
        $heading = array_search('Peringkat', array_column($rows, 0), true);

        return $heading !== false
            && $rows[$heading][3] === 'Pretest PG (maks 10)'
            && $rows[$heading + 1][1] === 'Angel Rondonuwu' && $rows[$heading + 1][5] == 17 && $rows[$heading + 1][7] == 85
            && $rows[$heading + 3][1] === 'Cindy Sondakh' && $rows[$heading + 3][4] === null
            && str_contains($rows[$heading + 3][9], '1 bagian belum dikerjakan');
    });
});

test('pilih semua quiz kelas lewat tombol cepat', function () {
    Livewire::actingAs($this->teacher)->test('pages::teach.score-recap')
        ->call('selectType', 'semua')
        ->assertSet('quizIds', [(string) $this->pg->id, (string) $this->esai->id, (string) $this->other->id])
        ->call('selectType', 'quiz')
        ->assertSet('quizIds', [(string) $this->other->id]);
});

test('tab rapor menampilkan akumulasi nilai semua peserta dan unduh Excel', function () {
    Excel::fake();

    Livewire::actingAs($this->teacher)->withQueryParams(['tab' => 'rapor'])->test('pages::teach.score-recap')
        ->assertSeeInOrder(['Angel Rondonuwu', 'Brian Lumowa', 'Cindy Sondakh'])
        ->assertSee(route('teach.report-card', [$this->classroom, $this->a]))
        ->call('export');

    Excel::assertDownloaded('rapor-nilai-peserta-kelas-mandarin-a-2026-11-20.xlsx');
});

test('rapor satu peserta tampil di layar dan bisa diunduh PDF', function () {
    Livewire::actingAs($this->teacher)->test('pages::teach.report-card', ['classroom' => $this->classroom, 'user' => $this->a])
        ->assertSee('Angel Rondonuwu')
        ->assertSeeInOrder(['Pretest – posttest', '85,0', 'Pretest PG', '8/10', '80,0', 'Pretest Esai', '9/10', '90,0'])
        ->assertSee('Quiz Kosakata 1')
        ->assertSee('Belum ada penilaian periodik');

    $this->actingAs($this->teacher)->get(route('teach.report-card.pdf', [$this->classroom, $this->a]))
        ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('content-disposition', 'attachment; filename="rapor-angel-rondonuwu-2026-11-20.pdf"');

    $this->actingAs($this->teacher)->get(route('teach.report-card', [$this->classroom, $this->b]))
        ->assertOk()->assertSee('menunggu dinilai');
});

test('hak akses: pengajar lain dan peserta ditolak, pimpinan dan admin boleh melihat', function () {
    $otherTeacher = teacher();

    $this->actingAs($otherTeacher)->get(route('teach.report-card', [$this->classroom, $this->a]))->assertForbidden();
    $this->actingAs($otherTeacher)->get(route('teach.report-card.pdf', [$this->classroom, $this->a]))->assertForbidden();
    Livewire::actingAs($otherTeacher)->test('pages::teach.score-recap')->assertSet('classroom', null)->assertDontSee('Angel Rondonuwu');

    $this->actingAs($this->a)->get(route('teach.score-recap'))->assertForbidden();
    $this->actingAs($this->a)->get(route('teach.report-card', [$this->classroom, $this->a]))->assertForbidden();

    // Bukan peserta kelas ini.
    $this->actingAs($this->teacher)->get(route('teach.report-card', [$this->classroom, $otherTeacher]))->assertNotFound();

    $this->actingAs(leader())->get(route('teach.score-recap', ['kelas' => $this->classroom->id]))->assertOk()->assertSee('Angel Rondonuwu');
    $this->actingAs(leader())->get(route('teach.report-card', [$this->classroom, $this->a]))->assertOk();
    $this->actingAs(programAdmin())->get(route('teach.report-card.pdf', [$this->classroom, $this->a]))->assertOk();
});
