<?php

use App\Actions\Announcements\ManageAnnouncements;
use App\Enums\AnnouncementSource;
use App\Enums\StaffUnit;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Classroom;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

function announce(User $by, array $data): Announcement
{
    return app(ManageAnnouncements::class)->save(array_merge([
        'title' => 'Info penting', 'body' => 'Isi pengumuman untuk diuji.', 'publish' => 'now',
    ], $data), $by);
}

test('sumber pengumuman sesuai peran: pengajar, asrama, tim kerja', function () {
    expect(AnnouncementSource::allowedFor(teacher()))->toBe([AnnouncementSource::Teacher])
        ->and(AnnouncementSource::allowedFor(staff([StaffUnit::Dormitory])))->toBe([AnnouncementSource::Dormitory])
        ->and(AnnouncementSource::allowedFor(staff([StaffUnit::Consumption])))->toBe([AnnouncementSource::Team])
        ->and(AnnouncementSource::allowedFor(programAdmin()))->toBe([AnnouncementSource::Team]);

    expect(fn () => announce(teacher(), ['source' => 'team', 'audience' => 'all']))
        ->toThrow(InvalidArgumentException::class, 'atas nama Tim Kerja');
});

test('pengumuman pengajar hanya ke kelasnya; peserta & wali kelas itu melihat, kelas lain tidak', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $other = Classroom::factory()->withParticipants(1)->create();
    $student = $classroom->participants()->first();
    $outsider = $other->participants()->first();
    $guardian = guardian();
    $student->allGuardians()->attach($guardian->id, ['relationship' => 'mother', 'verified_at' => now()]);

    expect(fn () => announce($classroom->teacher, ['source' => 'teacher', 'audience' => 'classroom', 'target_id' => $other->id]))
        ->toThrow(InvalidArgumentException::class, 'kelas yang diajarnya');
    expect(fn () => announce($classroom->teacher, ['source' => 'teacher', 'audience' => 'all']))
        ->toThrow(InvalidArgumentException::class, 'tidak tersedia');

    $a = announce($classroom->teacher, ['source' => 'teacher', 'audience' => 'classroom', 'target_id' => $classroom->id]);

    expect(Announcement::query()->visibleTo($student)->pluck('id')->all())->toBe([$a->id])
        ->and(Announcement::query()->visibleTo($guardian)->pluck('id')->all())->toBe([$a->id])
        ->and(Announcement::query()->visibleTo($outsider)->count())->toBe(0);

    Notification::assertSentTo([$student, $guardian], AnnouncementNotification::class);
    Notification::assertNotSentTo($outsider, AnnouncementNotification::class);
});

test('pengumuman asrama hanya untuk penghuni asrama itu', function () {
    $room = DormRoom::factory()->create();
    $resident = participant();
    DormRoomAssignment::factory()->create(['dorm_room_id' => $room->id, 'user_id' => $resident->id]);
    $elsewhere = participant();
    DormRoomAssignment::factory()->create(['user_id' => $elsewhere->id]);

    $a = announce(staff([StaffUnit::Dormitory]), ['source' => 'dormitory', 'audience' => 'dormitory', 'target_id' => $room->dormitory_id]);

    expect($a->source)->toBe(AnnouncementSource::Dormitory)
        ->and(Announcement::query()->visibleTo($resident)->count())->toBe(1)
        ->and(Announcement::query()->visibleTo($elsewhere)->count())->toBe(0);
});

test('pengumuman per peran: wali melihat, peserta tidak', function () {
    $a = announce(programAdmin(), ['source' => 'team', 'audience' => 'role', 'target_role' => 'guardian']);

    expect(Announcement::query()->visibleTo(guardian())->count())->toBe(1)
        ->and(Announcement::query()->visibleTo(participant())->count())->toBe(0);
});

test('draf tidak terlihat; terjadwal terbit lewat scheduler dan notifikasi sekali', function () {
    $student = participant();
    $draft = announce(programAdmin(), ['source' => 'team', 'audience' => 'all', 'publish' => 'draft']);
    $scheduled = announce(programAdmin(), ['source' => 'team', 'audience' => 'all', 'publish' => 'schedule', 'publish_at' => now()->addHour()->toDateTimeString()]);

    expect(Announcement::query()->visibleTo($student)->count())->toBe(0);
    Notification::assertNothingSentTo($student);

    $this->travel(2)->hours();
    $this->artisan('app:publish-announcements')->assertSuccessful();
    $this->artisan('app:publish-announcements')->assertSuccessful();

    expect(Announcement::query()->visibleTo($student)->pluck('id')->all())->toBe([$scheduled->id]);
    Notification::assertSentToTimes($student, AnnouncementNotification::class, 1);
    expect($draft->fresh()->published_at)->toBeNull();
});

test('halaman pengumuman: filter per sumber dan tanda sudah dibaca', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $student = $classroom->participants()->first();
    $team = announce(programAdmin(), ['source' => 'team', 'audience' => 'all', 'title' => 'Info dari tim kerja']);
    announce($classroom->teacher, ['source' => 'teacher', 'audience' => 'classroom', 'target_id' => $classroom->id, 'title' => 'Info dari pengajar']);

    Livewire::actingAs($student)->test('pages::announcements.index')
        ->assertSee('Info dari tim kerja')->assertSee('Info dari pengajar')
        ->set('source', 'teacher')
        ->assertSee('Info dari pengajar')->assertDontSee('Info dari tim kerja')
        ->call('read', $team->id);

    expect(AnnouncementRead::query()->where('user_id', $student->id)->where('announcement_id', $team->id)->exists())->toBeTrue();

    // Pengumuman yang tidak ditujukan padanya tidak bisa ditandai dibaca.
    $other = announce(programAdmin(), ['source' => 'team', 'audience' => 'role', 'target_role' => 'guardian']);
    Livewire::actingAs($student)->test('pages::announcements.index')->call('read', $other->id)->assertNotFound();
});

test('halaman kelola: pengajar menulis untuk kelasnya; pimpinan & peserta tidak bisa mengelola', function () {
    $classroom = Classroom::factory()->create();
    $classroom->batch->update(['is_active' => true]);

    Livewire::actingAs($classroom->teacher)->test('pages::manage.announcements')
        ->call('create')
        ->assertSet('form.source', 'teacher')->assertSet('form.audience', 'classroom')
        ->assertSet('form.target_id', $classroom->id)
        ->set('form.title', 'Kuis kosakata besok')
        ->set('form.body', 'Siapkan 20 kosakata bab 3.')
        ->call('save')->assertHasNoErrors();

    expect(Announcement::query()->where('title', 'Kuis kosakata besok')->first()->source)->toBe(AnnouncementSource::Teacher);

    $this->actingAs(leader())->get(route('manage.announcements'))->assertForbidden();
    $this->actingAs(participant())->get(route('manage.announcements'))->assertForbidden();
    $this->actingAs(leader())->get(route('announcements.index'))->assertOk();
});

test('pengajar lain tidak bisa mengubah atau menghapus pengumuman orang lain', function () {
    $classroom = Classroom::factory()->create();
    $a = announce($classroom->teacher, ['source' => 'teacher', 'audience' => 'classroom', 'target_id' => $classroom->id]);

    Livewire::actingAs(teacher())->test('pages::manage.announcements')->call('delete', $a->id)->assertForbidden();
    Livewire::actingAs(programAdmin())->test('pages::manage.announcements')->call('delete', $a->id)->assertHasNoErrors();

    expect($a->fresh()->trashed())->toBeTrue();
});
