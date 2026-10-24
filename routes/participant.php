<?php

use Illuminate\Support\Facades\Route;

// Rute peserta
Route::middleware('role:participant')->group(function () {
    Route::livewire('profil/wajah', 'pages::participant.face-enroll')->name('face.enroll');
    Route::livewire('absen', 'pages::participant.attendance')->name('attendance.daily');
    Route::livewire('absen/riwayat', 'pages::participant.attendance-history')->name('attendance.history');
});
