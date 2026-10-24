<?php

use App\Models\User;
use Livewire\Livewire;

test('halaman profil tampil', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

test('pegawai bisa mengubah nama, email, dan nomor HP', function () {
    $user = teacher();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'Ibu Pengajar')
        ->set('email', 'Pengajar@Contoh.test')
        ->set('phone', '0812-1111-2222')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Ibu Pengajar')
        ->email->toBe('pengajar@contoh.test')
        ->phone->toBe('6281211112222');
});

test('peserta tidak bisa mengubah nama resmi tetapi bisa menyembunyikan nama di peringkat', function () {
    $user = participant();
    $original = $user->name;

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'Nama Lain')
        ->set('leaderboard_anonymous', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->name->toBe($original)
        ->leaderboard_anonymous->toBeTrue();
});

test('nomor HP yang sudah dipakai akun lain ditolak', function () {
    User::factory()->create(['phone' => '6281299998888']);
    $user = teacher();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('phone', '081299998888')
        ->call('save')
        ->assertHasErrors('phone');
});
