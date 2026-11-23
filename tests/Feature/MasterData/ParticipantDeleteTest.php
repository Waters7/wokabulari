<?php

use App\Actions\Participants\ImportParticipants;
use App\Models\Classroom;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->classroom = Classroom::factory()->withParticipants(3)->create();
    [$this->a, $this->b, $this->c] = $this->classroom->participants()->orderBy('users.id')->get()->all();
    $this->a->update(['name' => 'Angel Rondonuwu', 'username' => '2026001', 'email' => 'angel@sekolah.test']);
    $this->b->update(['name' => 'Brian Lumowa', 'username' => '2026002']);
    $this->c->update(['name' => 'Cindy Sondakh', 'username' => '2026003']);
});

test('hapus banyak peserta sekaligus: hilang dari daftar, keluar kamar, NIS dilepas, tercatat di log', function () {
    $room = DormRoom::factory()->create(['capacity' => 2]);
    DormRoomAssignment::query()->create(['dorm_room_id' => $room->id, 'user_id' => $this->a->id, 'check_in_date' => today()->subWeek()]);

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->set('selected', [$this->a->id, $this->b->id])
        ->assertSee('Hapus peserta')
        ->call('deleteSelected')
        ->assertSet('selected', [])
        ->assertDontSee('Angel Rondonuwu')
        ->assertSee('Cindy Sondakh');

    $a = User::withTrashed()->find($this->a->id);
    expect($a->trashed())->toBeTrue()
        ->and($a->username)->toBeNull()
        ->and($a->email)->toBeNull()
        ->and($a->deleted_identity)->toMatchArray(['username' => '2026001', 'email' => 'angel@sekolah.test'])
        ->and($a->identity('username'))->toBe('2026001')
        ->and(User::withTrashed()->find($this->b->id)->trashed())->toBeTrue()
        ->and(DormRoomAssignment::query()->where('user_id', $this->a->id)->value('check_out_date')?->toDateString())->toBe(today()->toDateString())
        ->and($this->classroom->participants()->count())->toBe(1);

    expect(Activity::query()->where('description', 'Menghapus peserta')->latest('id')->first()->properties['jumlah'])->toBe(2);
});

test('filter Terhapus menampilkan peserta terhapus; Pulihkan mengembalikan NIS dan email', function () {
    $admin = programAdmin();
    Livewire::actingAs($admin)->test('pages::master.participants')->call('delete', $this->a->id);

    Livewire::actingAs($admin)->withQueryParams(['status' => 'deleted'])->test('pages::master.participants')
        ->assertSee('Angel Rondonuwu')
        ->assertSee('2026001')
        ->assertSee('Terhapus')
        ->assertDontSee('Brian Lumowa')
        ->set('search', '2026001')->assertSee('Angel Rondonuwu')
        ->call('restore', $this->a->id)
        ->assertDontSee('Angel Rondonuwu');

    $this->a->refresh();
    expect($this->a->trashed())->toBeFalse()
        ->and($this->a->username)->toBe('2026001')
        ->and($this->a->email)->toBe('angel@sekolah.test')
        ->and($this->a->deleted_identity)->toBeNull();
});

test('NIS peserta terhapus bisa dipakai peserta baru; pemulihan ditolak bila NIS sudah dipakai', function () {
    $admin = programAdmin();
    Livewire::actingAs($admin)->test('pages::master.participants')->call('delete', $this->a->id);

    Livewire::actingAs($admin)->test('pages::master.participants')
        ->call('create')
        ->set('form.name', 'Angel Baru')
        ->set('form.username', '2026001')
        ->set('form.classroom_id', $this->classroom->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('username', '2026001')->value('name'))->toBe('Angel Baru');

    Livewire::actingAs($admin)->withQueryParams(['status' => 'deleted'])->test('pages::master.participants')
        ->set('selected', [$this->a->id])
        ->call('restoreSelected')
        ->assertSee('Angel Rondonuwu');

    expect(User::withTrashed()->find($this->a->id)->trashed())->toBeTrue();
});

test('impor ulang NIS peserta terhapus memulihkan akun lamanya', function () {
    Livewire::actingAs(programAdmin())->test('pages::master.participants')->call('delete', $this->b->id);

    $result = app(ImportParticipants::class)->handle([['nama' => 'Brian Lumowa', 'nis' => '2026002']], null, 'awal');

    $this->b->refresh();
    expect($this->b->trashed())->toBeFalse()
        ->and($this->b->username)->toBe('2026002')
        ->and($result->created)->toBe(0)
        ->and(User::query()->where('username', '2026002')->count())->toBe(1);
});

test('hanya pengelola peserta yang bisa menghapus dan memulihkan', function () {
    Livewire::actingAs(leader())->test('pages::master.participants')
        ->assertDontSee('Terhapus (bisa dipulihkan)')
        ->call('deleteSelected')->assertForbidden();

    Livewire::actingAs(leader())->test('pages::master.participants')->call('delete', $this->a->id)->assertForbidden();
    expect($this->a->fresh()->trashed())->toBeFalse();
});
