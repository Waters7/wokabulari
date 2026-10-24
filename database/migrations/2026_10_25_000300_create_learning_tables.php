<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('topic');
            $table->text('objectives')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('session_attendance_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6)->index();
            $table->timestamp('expires_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_code_id')->nullable()->constrained('session_attendance_codes')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['learning_session_id', 'user_id']);
        });

        Schema::create('session_materials', function (Blueprint $table) {
            $table->id();
            // Null = materi bank (belum ditautkan ke sesi)
            $table->foreignId('learning_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('level', 50)->nullable();
            $table->string('topic')->nullable();
            $table->string('type', 10);
            $table->string('file_path')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('visibility', 20)->default('immediate');
            $table->timestamp('visible_from')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('material_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->unique(['session_material_id', 'user_id']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_from')->index();
            $table->date('date_to')->index();
            $table->string('type', 20);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            // Diisi bila izin sakit dibuat otomatis dari laporan sakit asrama (Fase 8)
            $table->unsignedBigInteger('health_report_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('material_access_logs');
        Schema::dropIfExists('session_materials');
        Schema::dropIfExists('session_attendances');
        Schema::dropIfExists('session_attendance_codes');
        Schema::dropIfExists('learning_sessions');
    }
};
