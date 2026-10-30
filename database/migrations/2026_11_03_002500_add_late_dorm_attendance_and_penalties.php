<?php

use App\Enums\PointEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Absen asrama terlambat tetap bisa (ditandai is_late) dan aturan poin pengurang untuk terlambat absen harian & asrama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dorm_attendances', function (Blueprint $table) {
            $table->boolean('is_late')->default(false)->after('status');
        });

        // Instalasi berjalan: tambah aturan pengurang poin (instalasi baru lewat GamificationSeeder).
        if (DB::table('gamification_rules')->exists()) {
            foreach ([PointEvent::DailyAttendanceLate, PointEvent::DormAttendanceLate] as $event) {
                if (! DB::table('gamification_rules')->where('event_key', $event->value)->exists()) {
                    DB::table('gamification_rules')->insert([
                        'event_key' => $event->value, 'name' => $event->label(), 'category' => $event->category()->value,
                        'points' => $event->defaultPoints(), 'is_active' => $event->activeByDefault(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('gamification_rules')->whereIn('event_key', [PointEvent::DailyAttendanceLate->value, PointEvent::DormAttendanceLate->value])->delete();

        Schema::table('dorm_attendances', function (Blueprint $table) {
            $table->dropColumn('is_late');
        });
    }
};