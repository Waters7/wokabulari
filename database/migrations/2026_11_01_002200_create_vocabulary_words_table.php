<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank kosakata per bahasa untuk kartu kosakata harian peserta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Aman diulang: MySQL tidak membatalkan DDL, jadi tabel bisa sudah ada dari percobaan yang gagal (v1.8.0).
        if (! Schema::hasTable('vocabulary_words')) {
            $this->createTable();
        }

        // Instalasi berjalan: isi bank Mandarin awal bila masih kosong (instalasi baru lewat VocabularySeeder).
        $languageId = DB::table('languages')->where('code', 'zh')->value('id');
        if ($languageId && ! DB::table('vocabulary_words')->exists()) {
            $now = now();
            DB::table('vocabulary_words')->insert(collect(require database_path('vocabulary/zh.php'))
                ->map(fn (array $row, int $i) => [
                    'language_id' => $languageId, 'word' => $row[0], 'reading' => $row[1], 'meaning' => $row[2], 'topic' => $row[3],
                    'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ])->all());
        }
    }

    protected function createTable(): void
    {
        Schema::create('vocabulary_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('word', 100);
            $table->string('reading', 150)->nullable();
            $table->string('meaning');
            $table->text('example')->nullable();
            $table->text('example_meaning')->nullable();
            $table->string('topic', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['language_id', 'is_active', 'sort_order'], 'vocab_lang_active_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabulary_words');
    }
};
