<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal absen harian per angkatan; kosong = memakai pengaturan sistem (setiap hari).
        Schema::table('batches', function (Blueprint $table) {
            $table->json('attendance_days')->nullable()->after('passing_score');
            $table->time('attendance_opens_at')->nullable()->after('attendance_days');
            $table->time('attendance_late_after')->nullable()->after('attendance_opens_at');
            $table->time('attendance_closes_at')->nullable()->after('attendance_late_after');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn(['attendance_days', 'attendance_opens_at', 'attendance_late_after', 'attendance_closes_at']);
        });
    }
};
