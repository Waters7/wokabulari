<?php

use App\Actions\Dorm\ConfirmDormAttendance;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DormAttendanceStatus;
use App\Enums\PointEvent;
use App\Enums\StaffUnit;
use App\Events\DailyAttendanceRecorded;
use App\Events\ParticipantMarkedAbsent;
use App\Events\QuizCompleted;
use App\Events\SessionAttendanceRecorded;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\DormAttendance;
use App\Models\GamificationRule;
use App\Models\LearningSession;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Notifications\BadgeEarnedNotification;
use App\Notifications\LevelUpNotification;
use App\Services\Gamification\LeaderboardService;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\PointService;
use App\Services\Gamification\StreakService;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(GamificationSeeder::class);
    Notification::fake();
});

function presentToday(User $user, ?string $date = null, AttendanceStatus $status = AttendanceStatus::Present): DailyAttendance
{
    $date ??= today()->toDateString();

    return DailyAttendance::factory()->create(['user_id' => $user->id, 'date' => $date, 'status' => $status, 'checked_in_at' => Carbon::parse($date)->setTime(7, 0)]);
}

test('poin idempoten: event yang sama tidak menambah poin dua kali', function () {
    $user = participant();
    $attendance = presentToday($user);

    event(new DailyAttendanceRecorded($attendance));
    event(new DailyAttendanceRecorded($attendance));

    expect(PointTransaction::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(app(PointService::class)->total($user))->toBe(10);
});

test('absen terlambat tidak mendapat poin tepat waktu tetapi tetap menambah streak', function () {
    $user = participant();
    event(new DailyAttendanceRecorded(presentToday($user, status: AttendanceStatus::Late)));

    expect(app(PointService::class)->total($user))->toBe(0)
        ->and($user->streak->current_streak)->toBe(1);
});

test('aturan nonaktif tidak memberi poin; perubahan poin dan batas harian dihormati', function () {
    $user = participant();
    $points = app(PointService::class);

    GamificationRule::query()->where('event_key', 'daily_attendance_on_time')->update(['is_active' => false]);
    expect($points->award($user, PointEvent::DailyAttendanceOnTime, ['tes', 1]))->toBeNull();

    GamificationRule::query()->where('event_key', 'daily_attendance_on_time')->update(['is_active' => true, 'points' => 12, 'max_per_day' => 1]);
    $points->flushRules();
    expect($points->award($user, PointEvent::DailyAttendanceOnTime, ['tes', 2])->points)->toBe(12)
        ->and($points->award($user, PointEvent::DailyAttendanceOnTime, ['tes', 3]))->toBeNull();
});

test('quiz: skor ÷ 5 maks. 20 sekali per quiz; bonus skor sempurna sekali per quiz', function () {
    $user = participant();
    $quiz = Quiz::factory()->create();
    $attempt = fn (float $pct) => QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'user_id' => $user->id, 'started_at' => now(), 'expires_at' => now()->addHour(), 'submitted_at' => now(), 'percentage' => $pct, 'status' => AttemptStatus::Graded]);

    event(new QuizCompleted($attempt(90)));
    expect(app(PointService::class)->total($user))->toBe(18);

    // Mengulang tidak menambah poin pengerjaan, tetapi skor sempurna pertama kali tetap dapat bonus.
    event(new QuizCompleted($attempt(100)));
    expect(app(PointService::class)->total($user))->toBe(28);
    event(new QuizCompleted($attempt(100)));
    expect(app(PointService::class)->total($user))->toBe(28);

    $quiz2 = Quiz::factory()->create();
    event(new QuizCompleted(QuizAttempt::query()->create(['quiz_id' => $quiz2->id, 'user_id' => $user->id, 'started_at' => now(), 'expires_at' => now()->addHour(), 'submitted_at' => now(), 'percentage' => 100, 'status' => AttemptStatus::Graded])));
    expect(app(PointService::class)->total($user))->toBe(28 + 20 + 10);
});

test('poin sesi hanya dari input kode, bukan penandaan manual pengajar', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $user = $classroom->participants()->first();
    $session = LearningSession::factory()->for($classroom)->create();

    $manual = SessionAttendance::query()->create(['learning_session_id' => $session->id, 'user_id' => $user->id, 'status' => AttendanceStatus::Present, 'marked_by' => $classroom->teacher_id]);
    event(new SessionAttendanceRecorded($manual));
    expect(app(PointService::class)->total($user))->toBe(0);

    $manual->update(['attendance_code_id' => $session->codes()->create(['code' => 'ABC234', 'expires_at' => now()->addMinutes(15), 'is_active' => true, 'generated_by' => $classroom->teacher_id])->id]);
    event(new SessionAttendanceRecorded($manual->fresh()));
    expect(app(PointService::class)->total($user))->toBe(5);
});

test('absen asrama mandiri yang dikonfirmasi mendapat poin, apel manual tidak', function () {
    $manager = staff([StaffUnit::Dormitory]);
    $self = DormAttendance::factory()->create();
    $roll = participant();

    app(ConfirmDormAttendance::class)->confirmMany($manager, [$self->id]);
    app(ConfirmDormAttendance::class)->mark($manager, $roll, today(), DormAttendanceStatus::Confirmed, 'Apel manual');

    expect(app(PointService::class)->total($self->user))->toBe(5)
        ->and(app(PointService::class)->total($roll))->toBe(0);
});

test('pengurang poin alpa nonaktif secara bawaan dan berlaku bila diaktifkan', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $user = $classroom->participants()->first();
    $session = LearningSession::factory()->for($classroom)->create();

    event(new ParticipantMarkedAbsent($user, $session));
    expect(app(PointService::class)->total($user))->toBe(0);

    GamificationRule::query()->where('event_key', 'absent')->update(['is_active' => true]);
    event(new ParticipantMarkedAbsent($user, $session));
    expect(app(PointService::class)->total($user))->toBe(-10);
});

test('naik level mengirim notifikasi dan lencana terbuka sesuai kriteria (sekali saja)', function () {
    $user = participant();
    $points = app(PointService::class);

    $points->adjust($user, 40, 'Bonus awal', programAdmin());
    Notification::assertNotSentTo($user, BadgeEarnedNotification::class);

    $points->adjust($user, 270, 'Juara lomba', programAdmin());
    Notification::assertSentTo($user, LevelUpNotification::class, fn ($n) => $n->newLevel->name === 'Serai');
    Notification::assertSentTo($user, BadgeEarnedNotification::class, fn ($n) => $n->badge->slug === 'langkah-pertama');

    $points->adjust($user, 5, 'Tambahan', programAdmin());
    expect($user->badges()->where('slug', 'langkah-pertama')->count())->toBe(1)
        ->and(app(LevelService::class)->progress(315)['level']->name)->toBe('Serai');
});

test('lencana Raja Quiz setelah 3 skor sempurna', function () {
    $user = participant();
    foreach (range(1, 3) as $i) {
        app(PointService::class)->award($user, PointEvent::QuizPerfect, ['quiz', $i]);
    }

    expect($user->badges()->where('slug', 'raja-quiz')->exists())->toBeTrue();
});

test('streak: hari tanpa sesi tidak memutus, hari kelas terlewat memutus', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $user = $classroom->participants()->first();
    $streaks = app(StreakService::class);
    $monday = Carbon::parse('2026-10-19');

    // Senin, Selasa hadir; Rabu tidak ada sesi; Kamis hadir → tetap bersambung.
    foreach (['2026-10-19', '2026-10-20', '2026-10-22'] as $day) {
        LearningSession::factory()->for($classroom)->create(['date' => $day]);
        $streaks->touch($user, Carbon::parse($day));
    }
    expect($user->fresh()->streak->current_streak)->toBe(3);

    // Jumat ada sesi tapi tidak hadir → Sabtu dini hari streak putus.
    LearningSession::factory()->for($classroom)->create(['date' => '2026-10-23']);
    expect($streaks->resetMissed(Carbon::parse('2026-10-23')))->toBe(1)
        ->and($user->fresh()->streak->current_streak)->toBe(0)
        ->and($user->fresh()->streak->longest_streak)->toBe(3);
});

test('leaderboard: urutan poin, peringkat sama untuk poin sama, nama disembunyikan tampil inisial', function () {
    $classroom = Classroom::factory()->withParticipants(3)->create();
    $classroom->batch->update(['is_active' => true]);
    [$a, $b, $c] = $classroom->participants()->orderBy('users.id')->get();
    $c->update(['leaderboard_anonymous' => true]);
    $points = app(PointService::class);
    $points->adjust($a, 30, 'x', programAdmin());
    $points->adjust($b, 30, 'x', programAdmin());
    $points->adjust($c, 50, 'x', programAdmin());

    $rows = app(LeaderboardService::class)->classroom($classroom->fresh(), ...LeaderboardService::week());
    expect($rows->pluck('rank')->all())->toBe([1, 2, 2])
        ->and($rows->first()['user']->id)->toBe($c->id);

    Livewire::actingAs($a)->test('pages::leaderboard')
        ->assertSee($a->name)->assertSee($c->initials().'.')->assertDontSee($c->name);
});

test('piagam peserta terbaik untuk admin, bukan untuk peserta', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $classroom->batch->update(['is_active' => true]);
    app(PointService::class)->adjust($classroom->participants()->first(), 40, 'x', programAdmin());

    $this->actingAs(programAdmin())->get(route('leaderboard.charter'))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs(participant())->get(route('leaderboard.charter'))->assertForbidden();
});

test('halaman gamifikasi: admin menyesuaikan poin & aturan; peserta tidak bisa membuka', function () {
    $admin = programAdmin();
    $user = participant();
    $rule = GamificationRule::query()->where('event_key', 'session_code')->first();

    Livewire::actingAs($admin)->test('pages::master.point-components')
        ->set("rules.{$rule->id}.points", 7)->call('saveRule', $rule->id)->assertHasNoErrors();
    Livewire::actingAs($admin)->test('pages::manage.gamification')
        ->call('openAdjust')->set('adjust.user_id', $user->id)->set('adjust.points', -5)->set('adjust.reason', 'Tidak ikut kerja bakti')
        ->call('saveAdjust')->assertHasNoErrors();

    expect($rule->fresh()->points)->toBe(7)
        ->and(app(PointService::class)->total($user))->toBe(-5);

    $this->actingAs($user)->get(route('manage.gamification'))->assertForbidden();
    $this->actingAs($user)->get(route('points.index'))->assertOk()->assertSee('Tidak ikut kerja bakti');
    $this->actingAs(guardian())->get(route('points.index'))->assertForbidden();
});

test('backfill aman dijalankan ulang', function () {
    $user = participant();
    presentToday($user);

    $this->artisan('app:gamification-backfill')->assertSuccessful();
    $this->artisan('app:gamification-backfill')->assertSuccessful();

    expect(PointTransaction::query()->where('user_id', $user->id)->count())->toBe(1);
});
