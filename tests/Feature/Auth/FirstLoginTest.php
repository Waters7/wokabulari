<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('akun dengan kata sandi awal diarahkan ke halaman ganti kata sandi', function () {
    $user = User::factory()->participant()->create(['must_change_password' => true]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
});

test('kata sandi baru tidak boleh sama dengan kata sandi awal', function () {
    $user = User::factory()->participant()->create(['must_change_password' => true, 'password' => 'smkgoglobal']);

    Livewire::actingAs($user)
        ->test('pages::auth.change-password')
        ->set('password', 'smkgoglobal')
        ->set('password_confirmation', 'smkgoglobal')
        ->call('save')
        ->assertHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('setelah ganti kata sandi peserta bisa masuk beranda', function () {
    $user = User::factory()->participant()->create(['must_change_password' => true, 'password' => 'smkgoglobal']);

    Livewire::actingAs($user)
        ->test('pages::auth.change-password')
        ->set('password', 'rahasia-baru-123')
        ->set('password_confirmation', 'rahasia-baru-123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('rahasia-baru-123', $user->password))->toBeTrue();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('peserta yang belum menyetujui data pribadi diarahkan ke halaman persetujuan', function () {
    $user = User::factory()->participant()->create(['consented_at' => null, 'consent_version' => null]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('consent.show'));

    Livewire::actingAs($user)
        ->test('pages::consent')
        ->call('accept')
        ->assertHasErrors('agree')
        ->set('agree', true)
        ->call('accept')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh())
        ->consented_at->not->toBeNull()
        ->consent_version->toBe(config('wokabulari.consent_version'));
});

test('pegawai tidak perlu persetujuan data pribadi', function () {
    $this->actingAs(teacher())->get(route('dashboard'))->assertOk();
});
