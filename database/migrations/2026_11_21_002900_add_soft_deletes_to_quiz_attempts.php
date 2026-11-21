<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reset attempt quiz (peserta boleh mengerjakan ulang): attempt disembunyikan, bukan dihapus permanen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quiz_attempts', 'deleted_at')) {
            return;
        }

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
