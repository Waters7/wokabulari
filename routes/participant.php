<?php

use App\Http\Controllers\MaterialFileController;
use Illuminate\Support\Facades\Route;

// Rute peserta
Route::middleware('role:participant')->group(function () {
    Route::livewire('kelas-saya', 'pages::participant.hub')->name('participant.class');
    Route::livewire('asrama-saya', 'pages::participant.hub')->name('participant.dorm');

    Route::livewire('profil/wajah', 'pages::participant.face-enroll')->name('face.enroll');
    Route::livewire('absen', 'pages::participant.attendance')->name('attendance.daily');
    Route::livewire('absen/riwayat', 'pages::participant.attendance-history')->name('attendance.history');
    Route::livewire('kode-sesi', 'pages::participant.session-code')->name('sessions.code');
    Route::livewire('materi', 'pages::participant.materials')->name('materials.index');
    Route::livewire('izin', 'pages::participant.leave')->name('leave.index');
});

// Materi bisa dibuka peserta dan pengajar (otorisasi di policy)
Route::livewire('materi/{material}', 'pages::participant.material-show')->name('materials.show');
Route::get('materi/{material}/berkas', MaterialFileController::class)->name('materials.file')->middleware('signed');

Route::livewire('notifikasi', 'pages::notifications')->name('notifications.index');
