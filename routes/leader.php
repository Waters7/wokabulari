<?php

use Illuminate\Support\Facades\Route;

// Rute pimpinan & laporan
Route::livewire('rekap/rating-pengajar', 'pages::ratings.teachers')->name('manage.teacher-ratings')->middleware('can:rating.view-names');
