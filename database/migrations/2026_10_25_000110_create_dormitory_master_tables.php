<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dormitories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gender', 10);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity')->default(0);
            $table->time('curfew_time')->default('22:00');
            $table->time('check_window_start')->default('20:00');
            $table->time('check_window_end')->default('21:30');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dorm_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dormitory_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);
            $table->string('floor', 10)->nullable();
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['dormitory_id', 'number']);
        });

        Schema::create('dorm_room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dorm_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('dorm_room_assignments');
        Schema::dropIfExists('dorm_rooms');
        Schema::dropIfExists('dormitories');
    }
};
