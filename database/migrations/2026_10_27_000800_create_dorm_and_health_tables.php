<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hubungan wali ↔ peserta (dipakai persetujuan izin keluar & notifikasi; portal wali di Fase 9).
        Schema::create('guardian_participant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 20)->default('guardian');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['guardian_id', 'participant_id']);
        });

        Schema::create('dorm_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dormitory_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->timestamp('checked_in_at')->nullable();
            $table->string('selfie_path')->nullable();
            $table->decimal('face_distance', 6, 4)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->decimal('distance_m', 10, 2)->nullable();
            $table->string('status', 30)->index();
            $table->string('reject_reason')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['dormitory_id', 'date']);
        });

        Schema::create('dorm_exit_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('reason');
            $table->string('destination');
            $table->string('companion')->nullable();
            $table->string('contact_phone', 30);
            $table->timestamp('planned_departure');
            $table->timestamp('planned_return');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->index();
            $table->boolean('guardian_required')->default(false);
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('guardian_decided_at')->nullable();
            $table->string('guardian_note', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('approval_note', 500)->nullable();
            $table->timestamp('actual_departure')->nullable();
            $table->foreignId('departure_scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actual_return')->nullable();
            $table->foreignId('return_scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('was_late')->default(false);
            $table->timestamp('late_notified_at')->nullable();
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'planned_departure']);
        });

        Schema::create('dorm_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dorm_room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30);
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dorm_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dorm_room_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('condition', 20)->default('good');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('health_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dormitory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dorm_room_id')->nullable()->constrained()->nullOnDelete();
            $table->json('symptoms')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('severity', 20);
            $table->string('photo_path')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->boolean('needs_special_meal')->default(false);
            $table->string('status', 20)->default('reported')->index();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('health_report_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 20);
            $table->string('medicine')->nullable();
            $table->string('dosage')->nullable();
            $table->string('referral_place')->nullable();
            $table->text('condition_note')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_report_actions');
        Schema::dropIfExists('health_reports');
        Schema::dropIfExists('dorm_inventories');
        Schema::dropIfExists('dorm_requests');
        Schema::dropIfExists('dorm_exit_permits');
        Schema::dropIfExists('dorm_attendances');
        Schema::dropIfExists('guardian_participant');
    }
};
