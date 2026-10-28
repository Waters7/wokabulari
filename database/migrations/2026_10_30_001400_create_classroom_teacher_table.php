<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu kelas bisa diajar beberapa pengajar; classrooms.teacher_id tetap sebagai pengajar utama.
        Schema::create('classroom_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['classroom_id', 'user_id']);
        });

        // Pengajar utama yang sudah ada ikut menjadi anggota tim pengajar kelas.
        DB::table('classroom_teacher')->insertUsing(
            ['classroom_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('classrooms')->whereNotNull('teacher_id')->select('id', 'teacher_id', DB::raw('CURRENT_TIMESTAMP'), DB::raw('CURRENT_TIMESTAMP')),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_teacher');
    }
};
