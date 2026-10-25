<?php

use App\Models\User;
use Livewire\Livewire;

test('pengguna non-admin mengisi email lalu bisa login dengan email maupun No. HP', function (string $role) {
    $user = $role();
    $phone = $user->phone;

    Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('email', 'Saya.Baru@Contoh.TEST')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('saya.baru@contoh.test');

    auth()->logout();
    $this->post(route('login.store'), ['login' => 'saya.baru@contoh.test', 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $this->post(route('login.store'), ['login' => '0'.substr($phone, 2), 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
})->with([
    'peserta' => 'participant',
    'pengajar' => 'teacher',
    'wali' => 'guardian',
    'admin program' => 'programAdmin',
    'tim kerja' => 'staff',
]);

test('email super admin tidak bisa diubah dari halaman profil', function () {
    $admin = User::factory()->superAdmin()->create(['email' => 'admin@contoh.test']);

    expect($admin->can('updateLoginEmail', $admin))->toBeFalse();

    $this->actingAs($admin)->get(route('profile.edit'))->assertSee('Email akun admin dikelola lewat server.');

    Livewire::actingAs($admin)->test('pages::settings.profile')
        ->set('email', 'lain@contoh.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->fresh()->email)->toBe('admin@contoh.test');
});

test('pengguna tidak bisa mengubah email login akun lain', function () {
    expect(teacher()->can('updateLoginEmail', participant()))->toBeFalse();
});

test('email yang sudah dipakai akun lain ditolak', function () {
    User::factory()->create(['email' => 'dipakai@contoh.test']);

    Livewire::actingAs(participant())->test('pages::settings.profile')
        ->set('email', 'Dipakai@contoh.test')
        ->call('save')
        ->assertHasErrors(['email' => 'Email ini sudah dipakai akun lain.']);
});

test('No. HP dan email tidak boleh dikosongkan bersamaan bila tanpa NIS/NIP', function () {
    $user = guardian();

    Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('phone', '')
        ->set('email', '')
        ->call('save')
        ->assertHasErrors('phone');

    expect($user->fresh())->phone->not->toBeNull()->email->not->toBeNull();
});

test('peserta bisa mengisi email saat login pertama', function () {
    $user = User::factory()->participant()->create(['must_change_password' => true, 'password' => 'smkgoglobal', 'email' => null]);

    $this->actingAs($user)->get(route('password.change'))->assertSee('Email (opsional)');

    Livewire::actingAs($user)->test('pages::auth.change-password')
        ->set('password', 'rahasia-baru-123')
        ->set('password_confirmation', 'rahasia-baru-123')
        ->set('email', 'Peserta@Contoh.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh())->email->toBe('peserta@contoh.test')->must_change_password->toBeFalse();
});

test('email kosong saat login pertama tidak menghapus email yang sudah ada', function () {
    $user = User::factory()->participant()->create(['must_change_password' => true, 'password' => 'smkgoglobal', 'email' => 'lama@contoh.test']);

    Livewire::actingAs($user)->test('pages::auth.change-password')
        ->set('password', 'rahasia-baru-123')
        ->set('password_confirmation', 'rahasia-baru-123')
        ->set('email', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe('lama@contoh.test');
});

test('halaman login menjelaskan login dengan No. HP atau email', function () {
    $this->get(route('login'))->assertSee('No. HP, email, atau NIS/NIP')->assertSee('Masuk pakai No. HP atau email');
});
