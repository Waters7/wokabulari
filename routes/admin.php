<?php

use App\Http\Controllers\ParticipantTemplateController;
use Illuminate\Support\Facades\Route;

// Data master & program (admin program, super admin; pimpinan read-only untuk peserta)
Route::prefix('master')->name('master.')->group(function () {
    Route::livewire('angkatan', 'pages::master.batches')->name('batches')->middleware('can:batch.manage');
    Route::livewire('bahasa', 'pages::master.languages')->name('languages')->middleware('can:batch.manage');
    Route::livewire('lokasi', 'pages::master.locations')->name('locations')->middleware('can:location.manage');
    Route::livewire('kelas', 'pages::master.classrooms')->name('classrooms')->middleware('can:classroom.manage');
    Route::livewire('kelas/{classroom}', 'pages::master.classroom-show')->name('classrooms.show')->middleware('can:classroom.manage');
    Route::livewire('peserta', 'pages::master.participants')->name('participants')->middleware('can:participant.view');
    Route::livewire('peserta/{user}', 'pages::master.participant-show')->name('participants.show')->middleware('can:participant.view');
    Route::get('peserta-template', ParticipantTemplateController::class)->name('participants.template')->middleware('can:participant.manage');
    Route::livewire('pengguna', 'pages::master.users')->name('users')->middleware('can:user.manage');
    Route::livewire('asrama', 'pages::master.dormitories')->name('dormitories')->middleware('can:dormitory.manage');
    Route::livewire('asrama/{dormitory}', 'pages::master.dormitory-show')->name('dormitories.show')->middleware('can:dormitory.manage');
    Route::livewire('wali', 'pages::master.guardians')->name('guardians')->middleware('can:guardian.verify');
    Route::livewire('vendor', 'pages::master.vendors')->name('vendors')->middleware('can:vendor.manage');
});

// Sistem (super admin)
Route::prefix('sistem')->name('system.')->group(function () {
    Route::livewire('pengaturan', 'pages::system.settings')->name('settings')->middleware('can:setting.manage');
    Route::livewire('hak-akses', 'pages::system.roles')->name('roles')->middleware('can:role.manage');
    Route::livewire('audit', 'pages::system.audit')->name('audit')->middleware('can:audit.view');
});
