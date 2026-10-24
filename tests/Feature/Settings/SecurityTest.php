<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('halaman kata sandi tampil', function () {
    $this->actingAs(User::factory()->create())->get(route('security.edit'))->assertOk();
});

test('kata sandi bisa diubah', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'kata-sandi-baru')
        ->set('password_confirmation', 'kata-sandi-baru')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('kata-sandi-baru', $user->fresh()->password))->toBeTrue();
});

test('kata sandi lama harus benar', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'salah')
        ->set('password', 'kata-sandi-baru')
        ->set('password_confirmation', 'kata-sandi-baru')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});
