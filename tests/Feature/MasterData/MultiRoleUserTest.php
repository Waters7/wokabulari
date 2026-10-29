<?php

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Navigation;
use Livewire\Livewire;

test('anggota tim kerja bisa sekaligus menjadi pengajar', function () {
    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('create')
        ->set('form.name', 'Bu Rina Konsumsi')
        ->set('form.email', 'rina@contoh.test')
        ->set('form.password', 'rahasia123')
        ->set('form.roles', ['staff', 'teacher'])
        ->set('form.units', ['consumption'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'rina@contoh.test')->firstOrFail();
    expect($user->hasRole(['staff', 'teacher']))->toBeTrue()
        ->and($user->hasAllRoles(['staff', 'teacher']))->toBeTrue()
        ->and($user->can('meal.monitor'))->toBeTrue()
        ->and($user->can('session.manage'))->toBeTrue()
        ->and($user->role_label)->toBe('Pengajar · Tim Kerja');

    // Bisa dipilih sebagai pengajar kelas dan langsung punya akses mengajar.
    $classroom = Classroom::factory()->create();
    $classroom->teachers()->attach($user->id);
    expect($user->can('teach', $classroom))->toBeTrue();

    // Menu & akses cepat memuat tugas mengajar dan tugas unit.
    $routes = collect(app(Navigation::class)->sidebar($user))->flatMap(fn ($g) => $g['items'])->pluck('route');
    expect($routes)->toContain('teach.sessions.index', 'meals.monitor');

    // Akun baru wajib ganti sandi & setujui data dulu; anggap sudah.
    $user->forceFill(['must_change_password' => false, 'consented_at' => now(), 'consent_version' => config('wokabulari.consent_version')])->save();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Akses cepat');
});

test('role tim kerja wajib memilih unit', function () {
    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('create')
        ->set('form.name', 'Tanpa Unit')
        ->set('form.email', 'tanpa.unit@contoh.test')
        ->set('form.password', 'rahasia123')
        ->set('form.roles', ['teacher', 'staff'])
        ->set('form.units', [])
        ->call('save')
        ->assertHasErrors('form.units');

    // Pengajar saja tidak butuh unit.
    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('create')
        ->set('form.name', 'Pengajar Saja')
        ->set('form.email', 'pengajar.saja@contoh.test')
        ->set('form.password', 'rahasia123')
        ->set('form.roles', ['teacher'])
        ->call('save')
        ->assertHasNoErrors();
});

test('pengguna dengan beberapa role bisa diubah tanpa kehilangan role lain', function () {
    $user = User::factory()->staff()->create();
    $user->assignRole(Role::Teacher->value);

    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('edit', $user->id)
        ->assertSet('form.roles', fn ($roles) => collect($roles)->sort()->values()->all() === ['staff', 'teacher'])
        ->set('form.name', 'Nama Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->hasAllRoles(['staff', 'teacher']))->toBeTrue();
});
