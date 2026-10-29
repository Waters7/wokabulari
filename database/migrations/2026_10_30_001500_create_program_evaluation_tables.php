<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Survei/evaluasi (SPEC 3.21): dibuat tim kerja, ditujukan ke peserta & pengajar angkatan tertentu.
        Schema::create('program_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 20)->default('facility');
            $table->boolean('target_participants')->default(true);
            $table->boolean('target_teachers')->default(false);
            $table->boolean('is_anonymous')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'closes_at']);
        });

        Schema::create('batch_program_evaluation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->unique(['program_evaluation_id', 'batch_id']);
        });

        Schema::create('evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('aspect', 100)->nullable();
            $table->string('type', 20);
            $table->text('body');
            $table->json('options')->nullable();
            // Pertanyaan khusus peserta/pengajar, mis. fasilitas asrama hanya untuk peserta.
            $table->string('audience', 20)->default('all');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('evaluation_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_evaluation_id')->constrained()->cascadeOnDelete();
            // Null bila anonim; respondent_hash tetap mencegah pengisian ganda.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('respondent_hash', 64);
            $table->string('respondent_role', 20);
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['program_evaluation_id', 'respondent_hash']);
        });

        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_question_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->unique(['evaluation_response_id', 'evaluation_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
        Schema::dropIfExists('evaluation_responses');
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('batch_program_evaluation');
        Schema::dropIfExists('program_evaluations');
    }
};
