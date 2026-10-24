<?php

use Illuminate\Support\Facades\Route;

// Rute pengajar & pendampingan (admin program juga memakai rute "kelola")
Route::prefix('kelola')->name('manage.')->group(function () {
    Route::livewire('absen', 'pages::manage.attendance')->name('attendance.index')->middleware('can:attendance.approve');
});
