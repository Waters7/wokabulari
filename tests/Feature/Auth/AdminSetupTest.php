<?php

use App\Models\User;
use Database\Seeders\TrialSeeder;
use Illuminate\Support\Facades\Hash;

test('app:create-admin membuat super admin dengan username tanpa email', function () {
    $this->artisan('app:create-admin', ['--name' => 'Super Admin', '--username' => 'admin', '--email' => '', '--password' => 'rahasia-uji-123'])
        ->assertSuccessful();

    $admin = User::where('username', 'admin')->firstOrFail();
    expect($admin->isSuperAdmin())->toBeTrue()
        ->and($admin->email)->toBeNull();

    $this->post(route('login.store'), ['login' => 'admin', 'password' => 'rahasia-uji-123'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);
});

test('app:create-admin menolak username ganda', function () {
    User::factory()->create(['username' => 'admin']);

    $this->artisan('app:create-admin', ['--name' => 'X', '--username' => 'admin', '--email' => '', '--password' => 'rahasia-uji-123'])
        ->assertFailed();
});

test('TrialSeeder membuat super admin dari konfigurasi .env', function () {
    config(['wokabulari.trial_admin.username' => 'admin', 'wokabulari.trial_admin.password' => 'sandi-trial-uji']);

    (new TrialSeeder)->createTrialAdmin();

    $admin = User::where('username', 'admin')->firstOrFail();
    expect($admin->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('sandi-trial-uji', $admin->password))->toBeTrue()
        ->and($admin->must_change_password)->toBeFalse();
});

test('TrialSeeder tidak membuat admin bila kata sandi kosong', function () {
    config(['wokabulari.trial_admin.password' => null]);

    (new TrialSeeder)->createTrialAdmin();

    expect(User::where('username', 'admin')->exists())->toBeFalse();
});
