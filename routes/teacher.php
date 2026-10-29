<?php

use App\Http\Controllers\QuestionTemplateController;
use App\Http\Controllers\VocabularyTemplateController;
use Illuminate\Support\Facades\Route;

// Rute pengajar
Route::prefix('mengajar')->name('teach.')->group(function () {
    Route::livewire('sesi', 'pages::teach.sessions')->name('sessions.index')->middleware('can:viewAny,App\Models\LearningSession');
    Route::livewire('sesi/{session}', 'pages::teach.session-show')->name('sessions.show');
    Route::livewire('sesi/{session}/proyektor', 'pages::teach.session-projector')->name('sessions.projector');
    Route::livewire('bank-materi', 'pages::teach.materials-bank')->name('materials.bank')->middleware('can:material.manage');
    Route::livewire('penilaian', 'pages::teach.assessments')->name('assessments.index')->middleware('can:assessment.manage');
    Route::livewire('tugas', 'pages::teach.assignments')->name('assignments.index')->middleware('can:assignment.manage');
    Route::livewire('tugas/{assignment}', 'pages::teach.assignment-show')->name('assignments.show')->middleware('can:assignment.manage');

    Route::livewire('poin', 'pages::teach.points')->name('points')->middleware('can:point.award');
    Route::livewire('rating-saya', 'pages::teach.ratings')->name('ratings')->middleware('role:teacher');

    Route::middleware('can:quiz.manage')->group(function () {
        Route::livewire('bank-soal', 'pages::teach.question-banks')->name('questions.index');
        Route::get('bank-soal/template', QuestionTemplateController::class)->name('questions.template');
        Route::livewire('bank-soal/{bank}', 'pages::teach.question-bank')->name('questions.bank');
        Route::livewire('quiz', 'pages::teach.quizzes')->name('quizzes.index');
        Route::livewire('quiz/{quiz}', 'pages::teach.quiz-show')->name('quizzes.show');
        Route::livewire('perlu-dinilai', 'pages::teach.grading')->name('grading');
    });
});

// Pendampingan (pengajar & admin program)
Route::prefix('kelola')->name('manage.')->group(function () {
    Route::livewire('absen', 'pages::manage.attendance')->name('attendance.index')->middleware('can:attendance.approve');
    Route::livewire('izin', 'pages::manage.leave')->name('leave.index')->middleware('can:leave.approve');
    Route::livewire('kosakata', 'pages::manage.words')->name('words.index')->middleware('can:word.manage');
    Route::get('kosakata/template', VocabularyTemplateController::class)->name('words.template')->middleware('can:word.manage');
});
