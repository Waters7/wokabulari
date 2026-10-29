<?php

use App\Models\Badge;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\VocabularyWord;
use App\Notifications\BadgeEarnedNotification;
use App\Services\DailyVocabulary;
use Database\Seeders\VocabularySeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

afterEach(fn () => Carbon::setTestNow());

function mandarin(): Language
{
    return Language::query()->firstOrCreate(['code' => 'zh'], ['name' => 'Mandarin', 'exam_format' => 'HSK', 'reading_label' => 'Pinyin']);
}

test('bank Mandarin awal terisi dan 5 kartu bergilir tiap hari, kembali ke awal setelah habis', function () {
    $language = mandarin();
    $this->seed(VocabularySeeder::class);
    $total = VocabularyWord::query()->where('language_id', $language->id)->count();
    expect($total)->toBeGreaterThanOrEqual(100)
        ->and(VocabularyWord::query()->where('word', '医生')->value('reading'))->toBe('yīshēng');

    $daily = app(DailyVocabulary::class);
    $today = $daily->words($language, Carbon::parse('2026-11-02'));
    $tomorrow = $daily->words($language, Carbon::parse('2026-11-03'));

    expect($today)->toHaveCount(5)
        ->and($today->pluck('id')->intersect($tomorrow->pluck('id')))->toBeEmpty()
        ->and($daily->words($language, Carbon::parse('2026-11-02'))->pluck('id')->all())->toBe($today->pluck('id')->all());

    // Bank kecil: setelah kata habis, giliran kembali ke awal.
    $small = Language::factory()->create(['code' => 'xx']);
    VocabularyWord::factory()->count(7)->sequence(fn ($s) => ['sort_order' => $s->index + 1])->create(['language_id' => $small->id]);
    expect($daily->words($small, Carbon::parse('2026-01-02'))->pluck('sort_order')->all())->toBe([6, 7, 1, 2, 3]);
});

test('peserta melihat kartu kosakata bahasa kelasnya; bank kosong sementara memakai Mandarin', function () {
    $language = mandarin();
    VocabularyWord::factory()->count(6)->sequence(fn ($s) => ['sort_order' => $s->index + 1])->create(['language_id' => $language->id, 'word' => '医生', 'reading' => 'yīshēng', 'meaning' => 'Dokter']);

    $mandarinClass = Classroom::factory()->withParticipants(1)->create(['language_id' => $language->id]);
    $this->actingAs($mandarinClass->participants->first())->get(route('dashboard'))
        ->assertSee('data-test="daily-vocabulary"', false)
        ->assertSee('Kosakata hari ini · Mandarin')->assertSee('医生')->assertSee('yīshēng')->assertSee('Dokter');

    $german = Language::factory()->create(['code' => 'de', 'name' => 'Jerman']);
    $germanClass = Classroom::factory()->withParticipants(1)->create(['language_id' => $german->id]);
    $result = app(DailyVocabulary::class)->for($germanClass->participants->first());
    expect($result['language']->code)->toBe('zh')->and($result['fallback'])->toBeTrue();
});

test('pengajar mengelola bank kosakata; peserta tidak bisa', function () {
    $language = mandarin();

    Livewire::actingAs(teacher())->test('pages::manage.words', ['languageId' => $language->id])
        ->call('create')
        ->set('form.word', '护士')
        ->set('form.reading', 'hùshi')
        ->set('form.meaning', 'Perawat')
        ->set('form.topic', 'Kesehatan')
        ->call('save')->assertHasNoErrors()
        ->assertSee('护士');

    expect(VocabularyWord::query()->where('word', '护士')->sole())->meaning->toBe('Perawat')->is_active->toBeTrue();

    $this->actingAs(participant())->get(route('manage.words.index'))->assertForbidden();
});

test('lencana yang diraih bisa dibagikan; notifikasi lencana membuka kartu bagikan', function () {
    $participant = participant();
    [$earned, $locked] = Badge::factory()->count(2)->create(['is_active' => true])->all();
    $participant->badges()->attach($earned->id, ['earned_at' => now()]);

    Livewire::actingAs($participant)->withQueryParams(['bagikan' => $earned->id])->test('pages::participant.points')
        ->assertSet('showShare', true)
        ->assertSee('data-test="badge-share"', false)
        ->assertSee('Bagikan lencana')->assertSee('#SMKGoGlobal')
        ->call('share', $locked->id)
        ->assertSet('showShare', false)->assertSet('shareId', null);

    expect((new BadgeEarnedNotification($earned))->url())->toBe(route('points.index', ['bagikan' => $earned->id]));
});

test('data kosakata ikut ke image Docker dan migrasinya aman diulang', function () {
    // Berkas di folder yang diabaikan .dockerignore tidak ada di server produksi.
    $path = 'database/vocabulary/zh.php';
    $ignored = collect(file(base_path('.dockerignore'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->map(fn ($line) => trim($line))
        ->reject(fn ($line) => $line === '' || str_starts_with($line, '#') || str_starts_with($line, '!'))
        ->filter(fn ($pattern) => fnmatch($pattern, $path) || fnmatch(rtrim($pattern, '/').'/*', $path) || str_starts_with($path, rtrim($pattern, '/*').'/'));
    expect($ignored->all())->toBe([]);

    // Tabel sudah ada tetapi kosong (sisa percobaan gagal): migrasi mengisi sekali saja.
    mandarin();
    $migration = require database_path('migrations/2026_11_01_002200_create_vocabulary_words_table.php');
    $migration->up();
    $count = VocabularyWord::query()->count();
    $migration->up();

    expect($count)->toBe(count(require database_path('vocabulary/zh.php')))
        ->and(VocabularyWord::query()->count())->toBe($count);
});
