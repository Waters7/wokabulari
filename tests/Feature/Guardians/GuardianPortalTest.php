<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Actions\Guardians\ManageGuardians;
use App\Actions\Sessions\EndSession;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\PeriodType;
use App\Enums\PublishStatus;
use App\Enums\StaffUnit;
use App\Events\AssessmentPublished;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\DormRoomAssignment;
use App\Models\GuardianInvitation;
use App\Models\HealthReport;
use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use App\Models\User;
use App\Notifications\ChildAbsentNotification;
use App\Notifications\GuardianAssessmentNotification;
use App\Notifications\GuardianInvitationNotification;
use App\Notifications\GuardianLinkNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

function wardOf(User $guardian, ?User $participant = null, bool $verified = true): User
{
    $participant ??= participant();
    $participant->allGuardians()->attach($guardian->id, ['relationship' => 'mother', 'verified_at' => $verified ? now() : null]);

    return $participant;
}

test('admin mengundang wali; wali mendaftar dengan kode & nomor sama langsung terverifikasi', function () {
    $admin = programAdmin();
    $child = participant();

    $invitation = app(ManageGuardians::class)->invite($child, $admin, '081234500001', 'ibu@example.test', 'mother');
    Notification::assertSentTo(new AnonymousNotifiable, GuardianInvitationNotification::class);

    Livewire::test('pages::guardian.register', ['code' => $invitation->code])
        ->assertSet('childName', strtok($child->name, ' '))
        ->set('form.name', 'Ibu Maria Wenas')
        ->set('form.phone', '081234500001')
        ->set('form.relationship', 'mother')
        ->set('form.password', 'RahasiaWali123!')
        ->set('form.password_confirmation', 'RahasiaWali123!')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $guardian = User::query()->where('phone', '6281234500001')->first();
    expect($guardian->isGuardian())->toBeTrue()
        ->and($guardian->isGuardianOf($child))->toBeTrue()
        ->and($invitation->fresh()->used_at)->not->toBeNull();

    // Kode tidak bisa dipakai dua kali.
    expect(fn () => app(ManageGuardians::class)->findUsable($invitation->code))->toThrow(InvalidArgumentException::class);
});

test('kode dari peserta menunggu verifikasi admin sebelum wali bisa melihat data', function () {
    $child = participant();
    $admin = programAdmin();
    $code = app(ManageGuardians::class)->shareCode($child)->code;
    $guardian = guardian();

    app(ManageGuardians::class)->link($guardian, $code, 'father');

    expect($guardian->isGuardianOf($child))->toBeFalse();
    Notification::assertSentTo($admin, GuardianLinkNotification::class, fn ($n) => $n->event === 'pending');
    $this->actingAs($guardian)->get(route('guardian.attendance', ['anak' => $child->id]))->assertForbidden();

    Livewire::actingAs($admin)->test('pages::master.guardians')->call('verify', $guardian->id, $child->id);

    expect($guardian->fresh()->isGuardianOf($child))->toBeTrue();
    Notification::assertSentTo($guardian, GuardianLinkNotification::class, fn ($n) => $n->event === 'verified');
    $this->actingAs($guardian)->get(route('guardian.attendance', ['anak' => $child->id]))->assertOk();
});

test('kode kedaluwarsa ditolak', function () {
    $invitation = GuardianInvitation::factory()->expired()->create();

    expect(fn () => app(ManageGuardians::class)->link(guardian(), $invitation->code, 'mother'))
        ->toThrow(InvalidArgumentException::class, 'kedaluwarsa');
});

test('portal wali: tidak bisa melihat peserta lain', function () {
    $guardian = guardian();
    $child = wardOf($guardian);
    $other = participant();

    foreach (['guardian.child', 'guardian.attendance', 'guardian.grades', 'guardian.approvals'] as $route) {
        $this->actingAs($guardian)->get(route($route, ['anak' => $child->id]))->assertOk();
        $this->actingAs($guardian)->get(route($route, ['anak' => $other->id]))->assertForbidden();
    }

    Livewire::actingAs($guardian)->test('pages::guardian.attendance')->call('selectChild', $other->id)->assertForbidden();
});

test('portal wali tidak menampilkan selfie maupun rating anonim', function () {
    Storage::fake('private');
    $guardian = guardian();
    $child = wardOf($guardian);
    DailyAttendance::factory()->create(['user_id' => $child->id, 'date' => today(), 'selfie_path' => 'selfies/rahasia.jpg']);
    DormAttendance::factory()->create(['user_id' => $child->id, 'selfie_path' => 'dorm-selfies/rahasia.jpg']);

    foreach (['guardian.child', 'guardian.attendance', 'dashboard'] as $route) {
        $this->actingAs($guardian)->get(route($route))->assertOk()->assertDontSee('rahasia.jpg')->assertDontSee('/berkas');
    }

    $this->actingAs($guardian)->get(route('teach.ratings'))->assertForbidden();
    $this->actingAs($guardian)->get(route('manage.teacher-ratings'))->assertForbidden();
    $this->actingAs($guardian)->get(route('dorm.manage.attendance'))->assertForbidden();
});

test('wali menyetujui izin keluar anak lewat portal', function () {
    $guardian = guardian();
    $child = wardOf($guardian);
    DormRoomAssignment::factory()->create(['user_id' => $child->id]);
    $permit = app(ManageExitPermits::class)->submit($child, [
        'type' => ExitPermitType::Overnight->value, 'reason' => 'Acara keluarga', 'destination' => 'Bitung',
        'contact_phone' => '081234567890', 'planned_departure' => now()->addDay(), 'planned_return' => now()->addDays(2),
    ]);

    Livewire::actingAs($guardian)->test('pages::guardian.approvals')
        ->assertSee('Bitung')
        ->call('ask', $permit->id, true)
        ->call('decide')
        ->assertHasNoErrors();

    expect($permit->fresh()->status)->toBe(ExitPermitStatus::GuardianApproved);

    // Wali lain tidak bisa memutuskan izin anak ini.
    Livewire::actingAs(guardian())->test('pages::guardian.approvals')->call('ask', $permit->id, false)->assertForbidden();
});

test('wali dikabari saat anak alpa di sesi dan saat nilai bulanan terbit', function () {
    $guardian = guardian();
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $child = wardOf($guardian, $classroom->participants()->first());
    $session = LearningSession::factory()->for($classroom)->create(['date' => today()]);
    SessionDocumentation::factory()->for($session, 'session')->end()->create();

    app(EndSession::class)->handle($session, $classroom->teacher);
    Notification::assertSentTo($guardian, ChildAbsentNotification::class);

    $assessment = Assessment::factory()->create([
        'user_id' => $child->id, 'classroom_id' => $classroom->id, 'period_type' => PeriodType::Monthly,
        'visible_to_guardian' => true, 'status' => PublishStatus::Published, 'average' => 82, 'grade' => 'B',
    ]);
    event(new AssessmentPublished($assessment));
    Notification::assertSentTo($guardian, GuardianAssessmentNotification::class);
});

test('peserta membuat kode wali dari profil', function () {
    $child = participant();

    Livewire::actingAs($child)->test('pages::participant.guardians')->call('generate')->assertSee('Salin tautan untuk wali');

    expect(GuardianInvitation::query()->where('participant_id', $child->id)->where('created_by', $child->id)->count())->toBe(1);
    // Memanggil lagi memakai kode yang sama selama masih berlaku.
    app(ManageGuardians::class)->shareCode($child);
    expect(GuardianInvitation::query()->where('participant_id', $child->id)->count())->toBe(1);
});

test('akses halaman wali dan admin wali sesuai role', function () {
    $this->actingAs(programAdmin())->get(route('master.guardians'))->assertOk();
    $this->actingAs(teacher())->get(route('master.guardians'))->assertForbidden();
    $this->actingAs(participant())->get(route('guardian.child'))->assertForbidden();
    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('guardian.approvals'))->assertForbidden();
});

test('halaman daftar wali terbuka untuk tamu', function () {
    $this->get(route('guardian.register', 'ABCDEFGH'))->assertOk()->assertSee('Daftar akun wali');
});

test('wali hanya melihat laporan sakit anaknya sendiri', function () {
    $guardian = guardian();
    $child = wardOf($guardian);
    $own = HealthReport::factory()->create(['user_id' => $child->id]);
    $other = HealthReport::factory()->create();

    $this->actingAs($guardian)->get(route('health.show', $own))->assertOk();
    $this->actingAs($guardian)->get(route('health.show', $other))->assertForbidden();
    expect(DormExitPermit::query()->count())->toBe(0);
});
