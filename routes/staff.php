<?php

use Illuminate\Support\Facades\Route;

// Rute tim kerja (konsumsi, asrama, kesehatan)
Route::prefix('konsumsi')->name('meals.')->group(function () {
    Route::livewire('monitor', 'pages::meals.monitor')->name('monitor')->middleware('can:meal.monitor');
    Route::livewire('jadwal', 'pages::meals.schedules')->name('schedules')->middleware('can:meal.manage');
    Route::livewire('alergi', 'pages::meals.allergies')->name('allergies')->middleware('can:meal.monitor');
    Route::livewire('rating-vendor', 'pages::meals.ratings')->name('ratings')->middleware('can:rating.view-vendor');
});

Route::prefix('asrama/kelola')->name('dorm.manage.')->group(function () {
    Route::livewire('absen-malam', 'pages::dorm.manage-attendance')->name('attendance')->middleware('can:attendance.confirm-dorm');
    Route::livewire('izin-keluar', 'pages::dorm.manage-exits')->name('exits')->middleware('can:dorm-exit.approve');
    Route::livewire('pindai', 'pages::dorm.scan')->name('scan')->middleware('can:dorm-exit.approve');
    Route::livewire('keluhan', 'pages::dorm.manage-requests')->name('requests')->middleware('can:dorm-request.handle');
    Route::livewire('inventaris', 'pages::dorm.inventory')->name('inventory')->middleware('can:dorm-inventory.manage');
});

Route::livewire('kesehatan', 'pages::health.index')->name('health.index')->middleware('can:health.view-detail');
// Detail laporan: otorisasi lewat policy (peserta ybs., petugas, wali terverifikasi).
Route::livewire('kesehatan/{report}', 'pages::health.show')->name('health.show');
