<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            // Kontak orang tua/wali yang wajib diisi peserta (berbeda dari akun portal wali).
            $table->string('guardian_name', 150)->nullable()->after('emergency_contact_phone');
            $table->string('guardian_phone', 20)->nullable()->after('guardian_name');
            $table->string('guardian_relationship', 20)->nullable()->after('guardian_phone');
        });
    }

    public function down(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            $table->dropColumn(['guardian_name', 'guardian_phone', 'guardian_relationship']);
        });
    }
};
