<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biodata yang dilengkapi peserta sendiri di Profil → Biodata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            $table->string('birth_place', 100)->nullable()->after('gender');
            $table->text('address')->nullable()->after('birth_date');
            $table->string('school_grade', 20)->nullable()->after('major');
            // Data kesehatan: terenkripsi (cast encrypted), jadi bertipe text.
            $table->text('blood_type')->nullable()->after('allergies');
            $table->string('shirt_size', 5)->nullable()->after('target_score');
            $table->timestamp('biodata_updated_at')->nullable()->after('shirt_size');
        });
    }

    public function down(): void
    {
        Schema::table('participant_profiles', function (Blueprint $table) {
            $table->dropColumn(['birth_place', 'address', 'school_grade', 'blood_type', 'shirt_size', 'biodata_updated_at']);
        });
    }
};
