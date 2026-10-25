<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('language_id')->constrained();
            $table->string('name');
            $table->string('level', 50)->nullable();
            $table->string('topic')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->text('body');
            // Bacaan: pinyin / romaji (ditampilkan sebagai <ruby> sesuai pengaturan quiz)
            $table->text('reading')->nullable();
            $table->string('media_path')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('reading')->nullable();
            $table->boolean('is_correct')->default(false);
            // Pasangan kanan untuk soal menjodohkan
            $table->string('match_key')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('learning_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('aspect', 20)->default('vocabulary');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->decimal('passing_score', 5, 2)->default(70);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->string('result_visibility', 20)->default('immediately');
            $table->timestamp('results_released_at')->nullable();
            $table->string('reading_display', 10)->default('ruby');
            // Skala skor tryout (mis. HSK 1–2 = 200, HSK 3–4 = 300)
            $table->unsignedSmallInteger('score_scale')->default(100);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('points', 6, 2)->nullable();
            // Bagian tryout (mis. 听力 Listening / 阅读 Reading)
            $table->string('section', 50)->nullable();
            $table->unique(['quiz_id', 'question_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('tab_switch_count')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('device_info')->nullable();
            // Satu sesi login aktif saat mengerjakan
            $table->string('session_id', 100)->nullable();
            $table->json('question_order')->nullable();
            $table->json('option_order')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->string('audio_path')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['quiz_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_question');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
    }
};
