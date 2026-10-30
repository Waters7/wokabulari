<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aspek penilaian periodik menjadi master data: admin menambah aspek, pengajar memilih aspek per kelas.
 * Nilai lama (kolom listening … attitude) disalin ke assessment_scores; kolom lama dibiarkan (tidak dihapus).
 */
return new class extends Migration
{
    /** Tujuh aspek bawaan (kode = kolom lama). */
    protected const DEFAULTS = [
        ['listening', 'Menyimak', 'Memahami percakapan & audio.'],
        ['speaking', 'Berbicara', 'Kelancaran, pelafalan, keberanian berbicara.'],
        ['reading', 'Membaca', 'Memahami teks & bacaan.'],
        ['writing', 'Menulis', 'Menulis kalimat/teks dengan benar.'],
        ['vocabulary', 'Kosakata', 'Penguasaan kosakata.'],
        ['grammar', 'Tata bahasa', 'Ketepatan struktur kalimat.'],
        ['attitude', 'Sikap', 'Sikap, disiplin, dan partisipasi di kelas.'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('assessment_aspects')) {
            Schema::create('assessment_aspects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
                $table->string('code', 40)->nullable()->unique();
                $table->string('name', 60);
                $table->string('description', 200)->nullable();
                $table->string('source_aspect', 20)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assessment_scores')) {
            Schema::create('assessment_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assessment_aspect_id')->constrained()->restrictOnDelete();
                $table->decimal('score', 5, 2);
                $table->timestamps();
                $table->unique(['assessment_id', 'assessment_aspect_id']);
            });
        }

        if (! Schema::hasTable('classroom_assessment_aspect')) {
            Schema::create('classroom_assessment_aspect', function (Blueprint $table) {
                $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assessment_aspect_id')->constrained()->cascadeOnDelete();
                $table->primary(['classroom_id', 'assessment_aspect_id']);
            });
        }

        $now = now();
        foreach (self::DEFAULTS as $i => [$code, $name, $description]) {
            DB::table('assessment_aspects')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'description' => $description, 'source_aspect' => $code,
                'is_default' => true, 'is_active' => true, 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Salin nilai lama ke tabel baru (aman dijalankan ulang).
        $aspectIds = DB::table('assessment_aspects')->whereNotNull('code')->pluck('id', 'code');
        DB::table('assessments')->orderBy('id')->chunkById(500, function ($assessments) use ($aspectIds, $now) {
            $rows = [];
            foreach ($assessments as $assessment) {
                foreach ($aspectIds as $code => $aspectId) {
                    if (isset($assessment->{$code})) {
                        $rows[] = ['assessment_id' => $assessment->id, 'assessment_aspect_id' => $aspectId, 'score' => $assessment->{$code}, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
            }
            DB::table('assessment_scores')->insertOrIgnore($rows);
        });

        $permission = Permission::findOrCreate('assessment-aspect.manage', 'web');
        Role::query()->where('name', 'program_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_assessment_aspect');
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessment_aspects');
        Permission::query()->where('name', 'assessment-aspect.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
