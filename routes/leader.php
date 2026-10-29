<?php

use Illuminate\Support\Facades\Route;

// Rute pimpinan & laporan (baca saja)
Route::livewire('dashboard-eksekutif', 'pages::leader.dashboard')->name('leader.dashboard')->middleware('can:dashboard.leader');
Route::livewire('laporan', 'pages::reports.index')->name('reports.index')->middleware('can:report.view');
Route::livewire('rekap/rating-pengajar', 'pages::ratings.teachers')->name('manage.teacher-ratings')->middleware('can:rating.view-names');
