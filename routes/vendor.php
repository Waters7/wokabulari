<?php

use Illuminate\Support\Facades\Route;

// Vendor makanan: laporan pengantaran
Route::prefix('vendor')->name('vendor.')->middleware('role:vendor')->group(function () {
    Route::livewire('pengantaran', 'pages::vendor.deliveries')->name('deliveries.index');
    Route::livewire('pengantaran/lapor', 'pages::vendor.delivery-form')->name('deliveries.create');
});
