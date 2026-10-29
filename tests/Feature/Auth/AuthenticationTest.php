<?php

use App\Models\User;

test('halaman login tampil dengan identitas Wokabulari', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Kaya Kosakata, Torang Go Global!')
        ->assertSee('images/brand/logo-horizontal.svg', false)
        ->assertSee('images/logos/poltekkes.png', false);
});

test('login dengan email', function () {
    $user = User::factory()->programAdmin()->create(['email' => 'admin@contoh.test']);

    $this->post(route('login.store'), ['login' => 'admin@contoh.test', 'password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login dengan NIS/NIP', function () {
    $user = User::factory()->teacher()->create(['username' => '198001012005011001']);

    $this->post(route('login.store'), ['login' => '198001012005011001', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('login dengan nomor HP dalam format 08…', function () {
    $user = User::factory()->participant()->create(['phone' => '6285824691907']);

    $this->post(route('login.store'), ['login' => '0858-2469-1907', 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('kata sandi salah ditolak', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'salah'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('akun nonaktif tidak bisa login', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('pengguna bisa keluar', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
