<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_component_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('aspect', 20);
            $table->decimal('weight', 5, 2);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->timestamps();
        });

        Schema::create('session_assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('aspect', 20);
            $table->decimal('weight', 5, 2);
            $table->decimal('max_score', 6, 2)->default(100);
            // manual | quiz | assignment — komponen otomatis tidak bisa diedit manual
            $table->string('source', 20)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['source', 'source_id']);
        });

        Schema::create('session_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_assessment_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['session_assessment_component_id', 'user_id']);
        });

        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aspect', 20)->nullable();
            $table->text('note');
            $table->boolean('visible_to_guardian')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('period_type', 10);
            $table->date('period_start')->index();
            $table->date('period_end');
            foreach (['listening', 'speaking', 'reading', 'writing', 'vocabulary', 'grammar', 'attitude'] as $aspect) {
                $table->decimal($aspect, 5, 2)->nullable();
            }
            $table->decimal('average', 5, 2)->nullable();
            $table->string('grade', 2)->nullable();
            $table->text('feedback')->nullable();
            $table->boolean('visible_to_guardian')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['classroom_id', 'user_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('session_notes');
        Schema::dropIfExists('session_scores');
        Schema::dropIfExists('session_assessment_components');
        Schema::dropIfExists('assessment_component_templates');
    }
};
