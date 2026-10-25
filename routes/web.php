<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::view('offline', 'offline')->name('offline');

require __DIR__.'/public.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::livewire('ganti-kata-sandi', 'pages::auth.change-password')->name('password.change');

    Route::middleware('password.changed')->group(function () {
        Route::livewire('persetujuan-data', 'pages::consent')->name('consent.show');

        Route::middleware('consent')->group(function () {
            // Peserta wajib mengisi kontak orang tua/wali dulu.
            Route::livewire('kontak-orang-tua', 'pages::participant.guardian-contact')->name('guardian-contact.edit');

            Route::middleware('guardian.contact')->group(function () {
                Route::livewire('beranda', 'pages::dashboard')->name('dashboard');

                require __DIR__.'/settings.php';
                require __DIR__.'/participant.php';
                require __DIR__.'/teacher.php';
                require __DIR__.'/staff.php';
                require __DIR__.'/guardian.php';
                require __DIR__.'/admin.php';
                require __DIR__.'/leader.php';
            });
        });
    });
});
