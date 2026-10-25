<?php

use Illuminate\Support\Facades\Route;

// Rute tim kerja (konsumsi, asrama, kesehatan)
Route::prefix('konsumsi')->name('meals.')->group(function () {
    Route::livewire('monitor', 'pages::meals.monitor')->name('monitor')->middleware('can:meal.monitor');
    Route::livewire('jadwal', 'pages::meals.schedules')->name('schedules')->middleware('can:meal.manage');
    Route::livewire('alergi', 'pages::meals.allergies')->name('allergies')->middleware('can:meal.monitor');
    Route::livewire('rating-vendor', 'pages::meals.ratings')->name('ratings')->middleware('can:rating.view-vendor');
});
