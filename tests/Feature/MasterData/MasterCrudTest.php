<?php

use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin program membuat dan mengubah angkatan', function () {
    $admin = programAdmin();

    Livewire::actingAs($admin)->test('pages::master.batches')
        ->call('create')
        ->set('form.name', 'Angkatan Uji')
        ->set('form.start_date', '2026-11-01')
        ->set('form.end_date', '2026-10-01')
        ->call('save')
        ->assertHasErrors('form.end_date')
        ->set('form.end_date', '2027-01-31')
        ->call('save')
        ->assertHasNoErrors();

    $batch = Batch::where('name', 'Angkatan Uji')->firstOrFail();
    expect($batch->attendance_target)->toBe(80);

    Livewire::actingAs($admin)->test('pages::master.batches')
        ->call('edit', $batch->id)
        ->set('form.attendance_target', 85)
        ->call('save')
        ->assertHasNoErrors();

    expect($batch->fresh()->attendance_target)->toBe(85);
});

test('angkatan yang masih punya kelas tidak bisa dihapus', function () {
    $classroom = Classroom::factory()->create();

    Livewire::actingAs(programAdmin())->test('pages::master.batches')
        ->call('delete', $classroom->batch_id);

    expect(Batch::find($classroom->batch_id))->not->toBeNull();
});

test('lokasi divalidasi koordinat dan radius', function () {
    Livewire::actingAs(programAdmin())->test('pages::master.locations')
        ->call('create')
        ->set('form.name', 'Kampus Uji')
        ->set('form.latitude', 120)
        ->set('form.radius_m', 5)
        ->call('save')
        ->assertHasErrors(['form.latitude', 'form.radius_m'])
        ->set('form.latitude', 1.46)
        ->set('form.radius_m', 150)
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::where('name', 'Kampus Uji')->exists())->toBeTrue();
});

test('kode bahasa harus unik', function () {
    Language::factory()->english()->create();

    Livewire::actingAs(programAdmin())->test('pages::master.languages')
        ->call('create')
        ->set('form.code', 'en')
        ->set('form.name', 'Inggris 2')
        ->call('save')
        ->assertHasErrors('form.code');
});

test('admin menambah peserta baru dengan kata sandi awal dan wajib ganti sandi', function () {
    config(['wokabulari.participant_default_password' => 'sandi-awal']);
    $classroom = Classroom::factory()->create();

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->call('create')
        ->set('form.name', 'Peserta Baru')
        ->set('form.phone', '0812-3456-7890')
        ->set('form.classroom_id', $classroom->id)
        ->set('form.allergies', 'Udang')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('phone', '6281234567890')->firstOrFail();
    expect($user->isParticipant())->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->classrooms()->first()->id)->toBe($classroom->id)
        ->and($user->profile->allergies)->toBe('Udang');

    auth()->guard('web')->logout();
    $this->post(route('login.store'), ['login' => '081234567890', 'password' => 'sandi-awal']);
    $this->assertAuthenticatedAs($user);
});

test('admin mengatur ulang kata sandi peserta ke sandi awal', function () {
    config(['wokabulari.participant_default_password' => 'sandi-awal']);
    $participant = participant();

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->call('resetPassword', $participant->id);

    $participant->refresh();
    expect($participant->must_change_password)->toBeTrue()
        ->and(Hash::check('sandi-awal', $participant->password))->toBeTrue();
});

test('super admin membuat akun tim kerja dengan unit', function () {
    Livewire::actingAs(superAdmin())->test('pages::master.users')
        ->call('create')
        ->set('form.name', 'Petugas Asrama Baru')
        ->set('form.email', 'asrama.baru@contoh.test')
        ->set('form.password', 'rahasia123')
        ->set('form.roles', ['staff'])
        ->set('form.units', ['dormitory'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'asrama.baru@contoh.test')->firstOrFail();
    expect($user->hasRole('staff'))->toBeTrue()
        ->and($user->can('attendance.confirm-dorm'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
});

test('admin program tidak bisa memberi role super admin', function () {
    $admin = programAdmin();
    $admin->givePermissionTo('user.manage');

    Livewire::actingAs($admin)->test('pages::master.users')
        ->call('create')
        ->set('form.name', 'Penyusup')
        ->set('form.email', 'penyusup@contoh.test')
        ->set('form.password', 'rahasia123')
        ->set('form.roles', ['super_admin'])
        ->call('save')
        ->assertHasErrors('form.roles');
});

test('pengaturan sistem tersimpan dan terbaca kembali', function () {
    Livewire::actingAs(superAdmin())->test('pages::system.settings')
        ->set('values.attendance.late_after', '08:00')
        ->set('values.session.code_duration_minutes', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect(setting('attendance.late_after'))->toBe('08:00')
        ->and(setting('session.code_duration_minutes'))->toBe(20);
});
