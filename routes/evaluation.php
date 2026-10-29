<?php

use Illuminate\Support\Facades\Route;

// Responden survei: peserta & pengajar
Route::middleware('role:participant|teacher')->group(function () {
    Route::livewire('survei', 'pages::evaluations.index')->name('evaluations.index');
    Route::livewire('survei/{evaluation}/isi', 'pages::evaluations.fill')->name('evaluations.fill');
});
