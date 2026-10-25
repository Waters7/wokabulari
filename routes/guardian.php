<?php

use Illuminate\Support\Facades\Route;

// Rute orang tua/wali (data anak dibatasi lewat ResolvesGuardianChild)
Route::middleware('role:guardian')->prefix('wali')->name('guardian.')->group(function () {
    Route::livewire('anak', 'pages::guardian.child')->name('child');
    Route::livewire('kehadiran', 'pages::guardian.attendance')->name('attendance');
    Route::livewire('nilai', 'pages::guardian.grades')->name('grades');
    Route::livewire('persetujuan', 'pages::guardian.approvals')->name('approvals');
    Route::livewire('hubungkan', 'pages::guardian.link')->name('link');
});
