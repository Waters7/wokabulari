<?php

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Support\Navigation;
use App\Support\UserGuide;
use Livewire\Livewire;

test('panduan menampilkan panduan sesuai role; role lain lewat URL tidak bisa dibuka', function () {
    $participant = participant();

    $this->actingAs($participant)->get(route('guide'))->assertOk()
        ->assertSee('data-test="guide-participant"', false)->assertSee('Absen harian (wajah + lokasi)')
        ->assertDontSee('Input konsumsi saat makanan datang');

    Livewire::actingAs($participant)->withQueryParams(['untuk' => 'staff_consumption'])->test('pages::guide')
        ->assertSet('guide', 'participant');

    $labels = collect(app(Navigation::class)->sidebar($participant))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($labels)->toContain('Panduan Penggunaan');
});

test('pengguna multi-role mendapat panduan tiap role & unit; admin program bisa membaca semua', function () {
    $user = staff([StaffUnit::Consumption, StaffUnit::Dormitory]);
    $user->assignRole(Role::Teacher->value);

    expect(app(UserGuide::class)->keysFor($user))->toBe(['teacher', 'staff_consumption', 'staff_dormitory']);

    Livewire::actingAs($user)->test('pages::guide')
        ->assertSee('Pengajar')->assertSee('Tim konsumsi')->assertSee('Pengelola asrama')
        ->call('$set', 'guide', 'staff_consumption')->assertSee('Snack pagi/Snack sore');

    $keys = app(UserGuide::class)->keysFor(programAdmin());
    expect($keys[0])->toBe('program_admin')->and($keys)->toContain('participant', 'leader', 'guardian', 'staff_health')
        ->and(app(UserGuide::class)->keysFor(leader()))->toBe(['leader']);

    $this->actingAs(guardian())->get(route('guide'))->assertOk()->assertSee('Persetujuan izin');
});
