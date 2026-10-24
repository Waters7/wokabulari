<?php

use Illuminate\Support\Facades\Route;

// Rute peserta
Route::middleware('role:participant')->group(function () {
    Route::livewire('profil/wajah', 'pages::participant.face-enroll')->name('face.enroll');
});
