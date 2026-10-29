<?php

use App\Actions\Gamification\AwardPointComponent;
use App\Actions\Gamification\ManagePointComponents;
use App\Actions\Meals\ReceiveMeal;
use App\Enums\PointCategory;
use App\Enums\PointEvent;
use App\Enums\ReviewStatus;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\GamificationRule;
use App\Models\MealSchedule;
use App\Models\PointTransaction;
use App\Notifications\PointComponentProposedNotification;
use App\Notifications\PointComponentReviewedNotification;
use App\Services\Gamification\PointService;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(GamificationSeeder::class);
    Notification::fake();
});

test('master komponen poin: aturan otomatis dikelompokkan, bisa diubah, dan komponen baru bisa ditambah', function () {
    $admin = programAdmin();
    $rule = GamificationRule::query()->where('event_key', PointEvent::MealReceipt->value)->sole();
    expect($rule->category)->toBe(PointCategory::Meal)->and($rule->points)->toBe(2);

    Livewire::actingAs($admin)->test('pages::master.point-components')
        ->assertSee('Isi tanda terima konsumsi')->assertSee('Konsumsi')->assertSee('Absen asrama tepat waktu')
        ->set("rules.{$rule->id}.points", 3)->call('saveRule', $rule->id)->assertHasNoErrors()
        ->call('create')
        ->set('form.name', 'Keaktifan bertanya')
        ->set('form.category', 'engagement')
        ->set('form.points', 5)
        ->set('form.max_per_day', 2)
        ->call('save')->assertHasNoErrors();

    expect($rule->fresh()->points)->toBe(3);
    $custom = GamificationRule::query()->custom()->sole();
    expect($custom->status)->toBe(ReviewStatus::Approved)->and($custom->isAwardable())->toBeTrue()->and($custom->event_key)->toBeNull();

    $this->actingAs(teacher())->get(route('master.point-components'))->assertForbidden();
    $this->actingAs(participant())->get(route('teach.points'))->assertForbidden();
});

test('pengajar mengajukan komponen; admin menyetujui dengan poin disesuaikan atau menolak dengan alasan', function () {
    $teacher = teacher();
    $admin = programAdmin();

    Livewire::actingAs($teacher)->test('pages::teach.points')
        ->call('openPropose')
        ->set('proposal.name', 'Juara quiz kosakata')
        ->set('proposal.category', 'quiz')
        ->set('proposal.points', 30)
        ->set('proposal.description', 'Untuk 3 skor tertinggi quiz kosakata mingguan.')
        ->call('propose')->assertHasNoErrors();

    $proposal = GamificationRule::query()->custom()->sole();
    expect($proposal->status)->toBe(ReviewStatus::Pending)->and($proposal->isAwardable())->toBeFalse();
    Notification::assertSentTo($admin, PointComponentProposedNotification::class);

    Livewire::actingAs($admin)->test('pages::master.point-components')
        ->assertSee('Juara quiz kosakata')
        ->set("approvePoints.{$proposal->id}", 20)->call('approve', $proposal->id)->assertHasNoErrors();

    expect($proposal->fresh())->status->toBe(ReviewStatus::Approved)->points->toBe(20);
    Notification::assertSentTo($teacher, PointComponentReviewedNotification::class);

    $other = app(ManagePointComponents::class)->propose(['name' => 'Bonus hadir', 'category' => 'attendance', 'points' => 50, 'description' => 'Hadir semua sesi minggu ini'], $teacher);
    Livewire::actingAs($admin)->test('pages::master.point-components')
        ->call('openReject', $other->id)->set('rejectNote', '')->call('reject')->assertHasErrors('rejectNote')
        ->set('rejectNote', 'Sudah tercakup absen harian')->call('reject')->assertHasNoErrors();
    expect($other->fresh()->status)->toBe(ReviewStatus::Rejected);

    // Kategori disiplin/konsumsi bukan untuk pengajuan pengajar.
    Livewire::actingAs($teacher)->test('pages::teach.points')->call('openPropose')
        ->set('proposal.name', 'Makan habis')->set('proposal.category', 'meal')->set('proposal.points', 5)->set('proposal.description', 'Menghabiskan makanan')
        ->call('propose')->assertHasErrors('proposal.category');
});

test('pengajar memberi komponen ke peserta kelasnya, terkait tugas; idempoten dan hanya untuk kelas sendiri', function () {
    $classroom = Classroom::factory()->withParticipants(3)->create();
    $classroom->batch->update(['is_active' => true]);
    $teacher = $classroom->teacher;
    $teacher->givePermissionTo('point.award');
    [$a, $b, $c] = $classroom->participants->all();
    $component = GamificationRule::factory()->customComponent(15)->create(['category' => PointCategory::Assignment, 'name' => 'Tugas proyek video']);
    $assignment = Assignment::factory()->create(['classroom_id' => $classroom->id, 'title' => 'Video perkenalan']);

    Livewire::actingAs($teacher)->test('pages::teach.points', ['classroomId' => $classroom->id])
        ->set('componentId', $component->id)
        ->assertSee('Video perkenalan')
        ->set('assignmentId', $assignment->id)
        ->set('participantIds', [(string) $a->id, (string) $b->id])
        ->call('award')->assertHasNoErrors();

    $points = app(PointService::class);
    expect($points->total($a))->toBe(15)->and($points->total($b))->toBe(15)->and($points->total($c))->toBe(0);
    expect(PointTransaction::query()->where('user_id', $a->id)->sole())
        ->event_key->toBe(PointEvent::Custom)
        ->description->toBe('Tugas proyek video · Tugas: Video perkenalan');

    $this->actingAs($a)->get(route('points.index'))->assertSee('Tugas proyek video');

    // Kelas lain & komponen yang belum disetujui ditolak.
    $otherClass = Classroom::factory()->withParticipants(1)->create();
    expect(fn () => app(AwardPointComponent::class)->handle($component, $otherClass, $otherClass->participants->pluck('id'), $teacher))
        ->toThrow(InvalidArgumentException::class, 'kelas yang Anda ajar');
    $component->update(['status' => ReviewStatus::Pending]);
    expect(fn () => app(AwardPointComponent::class)->handle($component, $classroom, [$c->id], $teacher))
        ->toThrow(InvalidArgumentException::class, 'belum disetujui');
});

test('batas per hari komponen tambahan dan hapus komponen yang sudah dipakai hanya menonaktifkan', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $component = GamificationRule::factory()->customComponent(5)->create(['max_per_day' => 1]);
    $admin = programAdmin();

    app(AwardPointComponent::class)->handle($component, $classroom, [$participant->id], $admin);
    ['awarded' => $second] = app(AwardPointComponent::class)->handle($component, $classroom, [$participant->id], $admin);

    expect($second)->toBe(0)->and(app(PointService::class)->total($participant))->toBe(5);
    expect(app(ManagePointComponents::class)->delete($component))->toBeFalse()
        ->and($component->fresh()->is_active)->toBeFalse();

    $unused = GamificationRule::factory()->customComponent()->create();
    expect(app(ManagePointComponents::class)->delete($unused))->toBeTrue();
});

test('tanda tangan terima konsumsi sendiri memberi poin, penerimaan diwakilkan tidak', function () {
    Storage::fake('private');
    $classroom = Classroom::factory()->withParticipants(2)->create();
    [$self, $proxied] = $classroom->participants->all();
    $schedule = MealSchedule::factory()->create(['date' => today(), 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()]);
    $signature = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));

    app(ReceiveMeal::class)->handle($schedule, $self, $signature);
    app(ReceiveMeal::class)->handle($schedule, $proxied, $signature, staff(), 'Sakit di kamar');

    expect(app(PointService::class)->total($self))->toBe(2)
        ->and(app(PointService::class)->total($proxied))->toBe(0);
});
