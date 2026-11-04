<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan perpanjangan waktu pengambilan/tanda terima (mis. makanan terlambat dari vendor).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('meal_schedules', 'extended_at')) {
            return;
        }

        Schema::table('meal_schedules', function (Blueprint $table) {
            $table->timestamp('extended_at')->nullable()->after('ends_at');
            $table->string('late_note', 200)->nullable()->after('extended_at');
            $table->foreignId('extended_by')->nullable()->after('late_note')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meal_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('extended_by');
            $table->dropColumn(['extended_at', 'late_note']);
        });
    }
};
