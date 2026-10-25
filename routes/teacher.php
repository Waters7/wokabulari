<?php

use Illuminate\Support\Facades\Route;

// Rute pengajar
Route::prefix('mengajar')->name('teach.')->group(function () {
    Route::livewire('sesi', 'pages::teach.sessions')->name('sessions.index')->middleware('can:viewAny,App\Models\LearningSession');
    Route::livewire('sesi/{session}', 'pages::teach.session-show')->name('sessions.show');
    Route::livewire('sesi/{session}/proyektor', 'pages::teach.session-projector')->name('sessions.projector');
    Route::livewire('bank-materi', 'pages::teach.materials-bank')->name('materials.bank')->middleware('can:material.manage');
    Route::livewire('penilaian', 'pages::teach.assessments')->name('assessments.index')->middleware('can:assessment.manage');
});

// Pendampingan (pengajar & admin program)
Route::prefix('kelola')->name('manage.')->group(function () {
    Route::livewire('absen', 'pages::manage.attendance')->name('attendance.index')->middleware('can:attendance.approve');
    Route::livewire('izin', 'pages::manage.leave')->name('leave.index')->middleware('can:leave.approve');
});
