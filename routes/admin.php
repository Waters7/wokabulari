<?php

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\ParticipantTemplateController;
use App\Models\ProgramEvaluation;
use Illuminate\Support\Facades\Route;

// Data master & program (admin program, super admin; pimpinan read-only untuk peserta)
Route::prefix('master')->name('master.')->group(function () {
    Route::livewire('angkatan', 'pages::master.batches')->name('batches')->middleware('can:batch.manage');
    Route::livewire('bahasa', 'pages::master.languages')->name('languages')->middleware('can:batch.manage');
    Route::livewire('aspek-penilaian', 'pages::master.assessment-aspects')->name('assessment-aspects')->middleware('can:assessment-aspect.manage');
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
    Route::livewire('komponen-poin', 'pages::master.point-components')->name('point-components')->middleware('can:gamification.manage');
});

// Kegiatan program
Route::prefix('kelola')->name('manage.')->group(function () {
    Route::livewire('gamifikasi', 'pages::manage.gamification')->name('gamification')->middleware('can:gamification.manage');

    // Survei/evaluasi: dibuat tim kerja; hasil juga bisa dilihat pimpinan (read-only).
    Route::livewire('survei', 'pages::manage.evaluations')->name('evaluations.index')->middleware('can:viewAny,'.ProgramEvaluation::class);
    Route::livewire('survei/baru', 'pages::manage.evaluation-form')->name('evaluations.create')->middleware('can:create,'.ProgramEvaluation::class);
    Route::livewire('survei/{evaluation}/ubah', 'pages::manage.evaluation-form')->name('evaluations.edit')->middleware('can:update,evaluation');
    Route::livewire('survei/{evaluation}', 'pages::manage.evaluation-results')->name('evaluations.show')->middleware('can:view,evaluation');
});

// Sistem (super admin)
Route::prefix('sistem')->name('system.')->group(function () {
    Route::livewire('pengaturan', 'pages::system.settings')->name('settings')->middleware('can:setting.manage');
    Route::get('cadangan/{name}', BackupDownloadController::class)->name('backups.download')->middleware('can:setting.manage')->where('name', 'wokabulari-[0-9]{8}-[0-9]{6}\.zip');
    Route::livewire('hak-akses', 'pages::system.roles')->name('roles')->middleware('can:role.manage');
    Route::livewire('audit', 'pages::system.audit')->name('audit')->middleware('can:audit.view');
});
