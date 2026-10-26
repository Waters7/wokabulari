<?php

use App\Actions\Classrooms\DistributeParticipants;
use App\Actions\Dorm\AssignRooms;
use App\Actions\Participants\ImportParticipants;
use App\Enums\Gender;
use App\Models\Classroom;
use App\Models\Dormitory;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use App\Services\DormRoster;
use App\Support\Navigation;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/** @return Collection<int, User> */
function participantsOfGender(Gender $gender, int $count, bool $resident = true): Collection
{
    return collect(range(1, $count))->map(function () use ($gender, $resident) {
        $user = participant();
        $user->profile->update(['gender' => $gender, 'lives_in_dorm' => $resident]);

        return $user->fresh(['profile', 'currentRoomAssignment.room']);
    });
}

test('admin memindahkan banyak peserta ke satu kelas sekaligus', function () {
    $from = Classroom::factory()->create();
    $to = Classroom::factory()->create(['batch_id' => $from->batch_id]);
    $users = participantsOfGender(Gender::Female, 3);
    $from->participants()->attach($users->first()->id, ['joined_at' => today()]);

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('selected', $users->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('openBulkClass')
        ->set('bulkClassroom', $to->id)
        ->call('applyBulkClass')
        ->assertHasNoErrors()
        ->assertSet('selected', []);

    expect($to->participants()->count())->toBe(3)
        ->and($from->participants()->count())->toBe(0);
});

test('bagi rata menyeimbangkan jumlah dan jenis kelamin per kelas', function () {
    $a = Classroom::factory()->create();
    $b = Classroom::factory()->create(['batch_id' => $a->batch_id]);
    // Kelas A sudah punya 2 peserta.
    $a->participants()->attach(participantsOfGender(Gender::Male, 2)->pluck('id')->mapWithKeys(fn ($id) => [$id => ['joined_at' => today()]])->all());
    $moving = participantsOfGender(Gender::Female, 4)->concat(participantsOfGender(Gender::Male, 4));

    $distribute = app(DistributeParticipants::class);
    $plan = $distribute->plan($moving, collect([$a, $b]));
    $summary = $distribute->summary($plan, $moving);

    // Total akhir seimbang: A 2 + 3 = 5, B 0 + 5 = 5; perempuan terbagi 2 dan 2.
    expect($summary[$a->id]['added'])->toBe(3)
        ->and($summary[$b->id]['added'])->toBe(5)
        ->and($summary[$a->id]['female'])->toBe(2)
        ->and($summary[$b->id]['female'])->toBe(2);

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('selected', $moving->pluck('id')->all())
        ->call('openBulkClass')
        ->set('bulkClassMode', 'distribute')
        ->set('bulkClassrooms', [$a->id, $b->id])
        ->assertSee('Pratinjau')
        ->call('applyBulkClass')
        ->assertHasNoErrors();

    expect($a->participants()->count())->toBe(5)->and($b->participants()->count())->toBe(5);
});

test('aksi massal kelas wajib memilih peserta dan kelas', function () {
    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->call('openBulkClass')
        ->call('applyBulkClass')
        ->assertHasErrors(['selected', 'classroom_id']);
});

test('isi kamar otomatis sesuai jenis kelamin dan kapasitas, sisanya dilewati dengan alasan', function () {
    $putri = Dormitory::factory()->create(['gender' => Gender::Female, 'name' => 'Asrama Putri']);
    $putra = Dormitory::factory()->create(['gender' => Gender::Male, 'name' => 'Asrama Putra']);
    $room10 = DormRoom::factory()->for($putri)->create(['number' => '10', 'capacity' => 2]);
    $room2 = DormRoom::factory()->for($putri)->create(['number' => '2', 'capacity' => 1]);
    $roomPutra = DormRoom::factory()->for($putra)->create(['number' => '1', 'capacity' => 1]);

    $women = participantsOfGender(Gender::Female, 4);
    $men = participantsOfGender(Gender::Male, 1);
    $commuter = participantsOfGender(Gender::Female, 1, resident: false);
    $noGender = participant();
    $noGender->profile->update(['gender' => null]);

    $plan = app(AssignRooms::class)->plan($women->concat($men)->concat($commuter)->push($noGender->fresh('profile')));

    // Kamar 2 diisi sebelum kamar 10 (urutan alami), total kapasitas putri 3.
    $womenRooms = collect($plan['placements'])->only($women->pluck('id')->all())->values()->all();
    expect(collect($plan['placements'])->countBy()->all())->toEqual([$room2->id => 1, $room10->id => 2, $roomPutra->id => 1])
        ->and($womenRooms[0])->toBe($room2->id)
        ->and($plan['skipped'])->toContain('Kamar asrama putri penuh', 'Tidak tinggal di asrama', 'Jenis kelamin belum diisi');

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('selected', $women->concat($men)->pluck('id')->all())
        ->call('openBulkDorm')
        ->assertSee('Pratinjau')
        ->call('applyBulkDorm')
        ->assertHasNoErrors();

    expect(DormRoomAssignment::query()->whereNull('check_out_date')->count())->toBe(4)
        ->and($room10->activeAssignments()->count())->toBe(2);
});

test('pindah ke kamar tertentu menolak kamar penuh dan asrama lain jenis kelamin', function () {
    $putri = Dormitory::factory()->create(['gender' => Gender::Female]);
    $room = DormRoom::factory()->for($putri)->create(['capacity' => 1]);
    [$first, $second] = participantsOfGender(Gender::Female, 2)->all();
    $man = participantsOfGender(Gender::Male, 1)->first();
    $rooms = app(AssignRooms::class);

    $rooms->assign($room, $first, programAdmin());

    expect(fn () => $rooms->assign($room, $second, programAdmin()))->toThrow(InvalidArgumentException::class, 'sudah penuh')
        ->and(fn () => $rooms->assign(DormRoom::factory()->for($putri)->create(), $man, programAdmin()))->toThrow(InvalidArgumentException::class, 'asrama Putri');
});

test('peserta ditandai tidak asrama keluar dari kamar, tidak ikut absen malam, dan menu asrama tersembunyi', function () {
    $dorm = Dormitory::factory()->create(['gender' => Gender::Male]);
    $room = DormRoom::factory()->for($dorm)->create();
    $user = participantsOfGender(Gender::Male, 1)->first();
    app(AssignRooms::class)->assign($room, $user, programAdmin());

    expect(app(DormRoster::class)->residents($dorm)->pluck('id'))->toContain($user->id);

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('selected', [$user->id])
        ->call('openBulkDorm')
        ->set('bulkDormMode', 'non_resident')
        ->call('applyBulkDorm')
        ->assertHasNoErrors();

    $user->refresh()->load('profile');
    expect($user->livesInDorm())->toBeFalse()
        ->and($room->activeAssignments()->count())->toBe(0)
        ->and(app(DormRoster::class)->residents($dorm))->toHaveCount(0);

    $routes = collect(app(Navigation::class)->sidebar($user))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($routes)->toContain('Konsumsi')->not->toContain('Absen Asrama')->not->toContain('Izin Keluar');
    expect(collect(app(Navigation::class)->bottom($user))->pluck('label'))->toContain('Konsumsi')->not->toContain('Asrama');

    $this->actingAs($user)->get(route('dorm.attendance'))->assertSee('tidak tinggal di asrama');
});

test('menempatkan peserta tidak asrama di kamar menjadikannya penghuni', function () {
    $dorm = Dormitory::factory()->create(['gender' => Gender::Female]);
    $user = participantsOfGender(Gender::Female, 1, resident: false)->first();

    app(AssignRooms::class)->assign(DormRoom::factory()->for($dorm)->create(), $user, programAdmin());

    expect($user->fresh('profile')->livesInDorm())->toBeTrue();
});

test('jenis kelamin diisi massal lalu kamar bisa diisi otomatis', function () {
    $dorm = Dormitory::factory()->create(['gender' => Gender::Female]);
    DormRoom::factory()->for($dorm)->create(['capacity' => 4]);
    $users = collect(range(1, 2))->map(function () {
        $user = participant();
        $user->profile->update(['gender' => null]);

        return $user;
    });

    $page = Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('status', 'no_gender')
        ->call('selectAllFiltered')
        ->call('openBulkDorm')
        ->set('bulkDormMode', 'gender')
        ->call('applyBulkDorm')
        ->assertHasErrors('gender')
        ->set('bulkGender', 'female')
        ->call('applyBulkDorm')
        ->assertHasNoErrors();

    expect($users->map(fn ($u) => $u->profile->fresh()->gender)->unique()->all())->toBe([Gender::Female]);

    $page->set('selected', $users->pluck('id')->all())
        ->call('openBulkDorm')
        ->call('applyBulkDorm')
        ->assertHasNoErrors();

    expect(DormRoomAssignment::query()->whereIn('user_id', $users->pluck('id'))->whereNull('check_out_date')->count())->toBe(2);
});

test('aksi massal asrama hanya untuk pemegang izin kelola asrama', function () {
    Livewire::actingAs(teacher())->test('pages::master.participants')
        ->call('openBulkDorm')
        ->assertForbidden();
});

test('filter belum dapat kamar dan impor kolom asrama', function () {
    $resident = participant();
    $commuter = participantsOfGender(Gender::Male, 1, resident: false)->first();

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('status', 'no_room')
        ->assertSee($resident->name)
        ->assertDontSee($commuter->name)
        ->call('selectAllFiltered')
        ->assertSet('selected', fn ($ids) => in_array($resident->id, $ids) && ! in_array($commuter->id, $ids));

    app(ImportParticipants::class)->handle([
        ['nama' => 'Peserta PP', 'no_hp' => '081277770000', 'asrama' => 'Tidak'],
    ], defaultPassword: 'sandi-awal');

    expect(User::query()->where('phone', '6281277770000')->first()->profile->lives_in_dorm)->toBeFalse();
});
