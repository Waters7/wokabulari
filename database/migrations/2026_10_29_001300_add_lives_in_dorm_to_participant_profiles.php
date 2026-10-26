<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            // Peserta tidak asrama (pulang-pergi) tidak ikut absen malam & urusan asrama.
            $table->boolean('lives_in_dorm')->default(true)->after('guardian_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            $table->dropColumn('lives_in_dorm');
        });
    }
};
