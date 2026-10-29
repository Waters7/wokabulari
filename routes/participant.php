<?php

use App\Http\Controllers\CharterController;
use App\Http\Controllers\MaterialFileController;
use Illuminate\Support\Facades\Route;

// Konsumsi: peserta dan tim yang ditandai menerima konsumsi.
Route::livewire('konsumsi', 'pages::meals.index')->name('meals.index')->middleware('can:receive,App\Models\MealSchedule');

// Rute peserta
Route::middleware('role:participant')->group(function () {
    Route::livewire('kelas-saya', 'pages::participant.hub')->name('participant.class');
    Route::livewire('asrama-saya', 'pages::participant.hub')->name('participant.dorm');

    Route::livewire('profil/wajah', 'pages::participant.face-enroll')->name('face.enroll');
    Route::livewire('absen', 'pages::participant.attendance')->name('attendance.daily');
    Route::livewire('absen/riwayat', 'pages::participant.attendance-history')->name('attendance.history');
    Route::livewire('kode-sesi', 'pages::participant.session-code')->name('sessions.code');
    Route::livewire('materi', 'pages::participant.materials')->name('materials.index');
    Route::livewire('izin', 'pages::participant.leave')->name('leave.index');
    Route::livewire('nilai', 'pages::participant.grades')->name('grades.index');
    Route::livewire('tugas', 'pages::participant.assignments')->name('assignments.index');
    Route::livewire('tugas/{assignment}', 'pages::participant.assignment-show')->name('assignments.show');
    Route::livewire('quiz', 'pages::participant.quizzes')->name('quizzes.index');
    Route::livewire('quiz/hasil/{attempt}', 'pages::participant.quiz-result')->name('quizzes.result');
    Route::livewire('quiz/{quiz}/kerjakan', 'pages::participant.quiz-take')->name('quizzes.take');
    Route::livewire('rating', 'pages::ratings.index')->name('ratings.index');
    Route::livewire('asrama/absen', 'pages::dorm.attendance')->name('dorm.attendance');
    Route::livewire('asrama/izin-keluar', 'pages::dorm.exits')->name('dorm.exit.index');
    Route::livewire('asrama/keluhan', 'pages::dorm.requests')->name('dorm.requests.index');
    Route::livewire('poin', 'pages::participant.points')->name('points.index');
    Route::livewire('profil/wali', 'pages::participant.guardians')->name('participant.guardians');
    Route::livewire('lapor-sakit', 'pages::health.report')->name('health.report');
});

// Materi bisa dibuka peserta dan pengajar (otorisasi di policy)
Route::livewire('materi/{material}', 'pages::participant.material-show')->name('materials.show');
Route::get('materi/{material}/berkas', MaterialFileController::class)->name('materials.file')->middleware('signed');

Route::livewire('notifikasi', 'pages::notifications')->name('notifications.index');

// Peringkat: peserta, pengajar, admin program, pimpinan (otorisasi di halaman)
Route::livewire('peringkat', 'pages::leaderboard')->name('leaderboard');
Route::get('peringkat/piagam', CharterController::class)->name('leaderboard.charter');

// Pengumuman: dibaca semua pengguna; dikelola tim kerja, pengajar, pengelola asrama, admin program
Route::livewire('pengumuman', 'pages::announcements.index')->name('announcements.index');
Route::livewire('kelola/pengumuman', 'pages::manage.announcements')->name('manage.announcements')->middleware('can:announcement.manage');
