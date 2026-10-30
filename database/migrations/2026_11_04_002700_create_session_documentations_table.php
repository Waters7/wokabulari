<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumentasi kelas (foto kamera/file) yang diunggah pengajar saat mulai dan akhir sesi. File di disk private.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('session_documentations')) {
            return;
        }

        Schema::create('session_documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_session_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 10);
            $table->string('source', 10);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['learning_session_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_documentations');
    }
};
