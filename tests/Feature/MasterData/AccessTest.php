<?php

use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\Dormitory;
use App\Models\User;
use App\Services\StaffUnitService;

$adminPages = [
    'angkatan' => 'master.batches',
    'bahasa' => 'master.languages',
    'lokasi' => 'master.locations',
    'kelas' => 'master.classrooms',
    'peserta' => 'master.participants',
    'pengguna' => 'master.users',
    'asrama' => 'master.dormitories',
    'vendor' => 'master.vendors',
    'pengaturan sistem' => 'system.settings',
    'hak akses' => 'system.roles',
    'audit' => 'system.audit',
];

test('super admin bisa membuka semua halaman master & sistem', function (string $route) {
    $this->actingAs(superAdmin())->get(route($route))->assertOk();
})->with($adminPages);

test('halaman detail kelas, peserta, dan asrama tampil untuk admin program', function () {
    $admin = programAdmin();
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $participant = $classroom->participants()->first();
    $dormitory = Dormitory::factory()->create();

    $this->actingAs($admin)->get(route('master.classrooms.show', $classroom))->assertOk()->assertSee($participant->name);
    $this->actingAs($admin)->get(route('master.participants.show', $participant))->assertOk();
    $this->actingAs($admin)->get(route('master.dormitories.show', $dormitory))->assertOk();
});

test('peserta tidak bisa membuka halaman admin', function (string $route) {
    $this->actingAs(participant())->get(route($route))->assertForbidden();
})->with($adminPages);

test('pimpinan read-only: bisa melihat peserta tetapi tidak bisa mengelola data master', function () {
    $leader = leader();

    $this->actingAs($leader)->get(route('master.participants'))->assertOk()->assertDontSee('wire:click="create"', false);
    $this->actingAs($leader)->get(route('master.batches'))->assertForbidden();
    $this->actingAs($leader)->get(route('master.users'))->assertForbidden();
    $this->actingAs($leader)->get(route('system.settings'))->assertForbidden();
});

test('pengajar tidak bisa mengubah pengaturan sistem atau data master', function () {
    $teacher = teacher();

    $this->actingAs($teacher)->get(route('system.settings'))->assertForbidden();
    $this->actingAs($teacher)->get(route('master.classrooms'))->assertForbidden();
});

test('tim kerja konsumsi hanya bisa membuka vendor, bukan asrama', function () {
    $staff = staff([StaffUnit::Consumption]);

    $this->actingAs($staff)->get(route('master.vendors'))->assertOk();
    $this->actingAs($staff)->get(route('master.dormitories'))->assertForbidden();
});

test('permission tim kerja mengikuti unit', function () {
    $staff = staff([StaffUnit::Dormitory, StaffUnit::Health]);

    expect($staff->can('attendance.confirm-dorm'))->toBeTrue()
        ->and($staff->can('health.view-detail'))->toBeTrue()
        ->and($staff->can('meal.manage'))->toBeFalse();

    app(StaffUnitService::class)->sync($staff, [StaffUnit::Consumption]);
    $staff = User::find($staff->id);

    expect($staff->can('meal.manage'))->toBeTrue()
        ->and($staff->can('attendance.confirm-dorm'))->toBeFalse();
});
