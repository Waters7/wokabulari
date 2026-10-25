<?php

// Rute publik (tanpa login)

use Illuminate\Support\Facades\Route;

// Pendaftaran wali dengan kode undangan
Route::livewire('wali/daftar/{code?}', 'pages::guardian.register')->name('guardian.register')->middleware('guest');
