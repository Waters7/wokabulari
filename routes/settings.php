<?php

use Illuminate\Support\Facades\Route;

Route::redirect('pengaturan', 'pengaturan/profil');

Route::livewire('pengaturan/profil', 'pages::settings.profile')->name('profile.edit');
Route::livewire('pengaturan/biodata', 'pages::settings.biodata')->name('biodata.edit')->middleware('role:participant');
Route::livewire('pengaturan/tampilan', 'pages::settings.appearance')->name('appearance.edit');
Route::livewire('pengaturan/keamanan', 'pages::settings.security')->name('security.edit');
