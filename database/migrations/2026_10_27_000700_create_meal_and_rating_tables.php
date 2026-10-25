<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('meal_type', 20);
            $table->foreignId('vendor_id')->constrained();
            $table->text('menu');
            // Rentang waktu pengambilan
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('portions_ordered')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['date', 'meal_type', 'vendor_id']);
        });

        Schema::create('meal_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signature_path');
            $table->timestamp('received_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Petugas yang mencatat penerimaan atas nama peserta (diwakilkan)
            $table->foreignId('proxy_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['meal_schedule_id', 'user_id']);
        });

        Schema::create('special_meal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Diisi otomatis dari laporan sakit asrama (Fase 8)
            $table->unsignedBigInteger('health_report_id')->nullable()->index();
            $table->date('date')->index();
            $table->string('meal_type', 20)->nullable();
            $table->text('request');
            $table->boolean('delivered_to_room')->default(false);
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start')->index();
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('taste')->nullable();
            $table->unsignedTinyInteger('portion')->nullable();
            $table->unsignedTinyInteger('cleanliness')->nullable();
            $table->unsignedTinyInteger('punctuality')->nullable();
            $table->text('comment')->nullable();
            $table->text('suggestion')->nullable();
            $table->timestamps();
            $table->unique(['vendor_id', 'user_id', 'week_start']);
        });

        Schema::create('teacher_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_start')->index();
            $table->unsignedTinyInteger('rating');
            $table->unsignedTinyInteger('mastery')->nullable();
            $table->unsignedTinyInteger('delivery')->nullable();
            $table->unsignedTinyInteger('discipline')->nullable();
            $table->unsignedTinyInteger('interaction')->nullable();
            $table->text('comment')->nullable();
            $table->text('suggestion')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
            $table->unique(['teacher_id', 'user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_ratings');
        Schema::dropIfExists('vendor_ratings');
        Schema::dropIfExists('special_meal_requests');
        Schema::dropIfExists('meal_receipts');
        Schema::dropIfExists('meal_schedules');
    }
};
