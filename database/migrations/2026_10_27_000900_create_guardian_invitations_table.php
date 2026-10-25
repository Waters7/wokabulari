<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('relationship', 20)->nullable();
            $table->string('code', 12)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_invitations');
    }
};
