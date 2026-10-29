<?php

use App\Exports\VocabularyTemplateExport;
use App\Models\Language;
use App\Models\VocabularyWord;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

function zhLanguage(): Language
{
    return Language::query()->firstOrCreate(['code' => 'zh'], ['name' => 'Mandarin', 'exam_format' => 'HSK', 'reading_label' => 'Pinyin']);
}

test('templat import bank kosakata berisi kolom, contoh sesuai bahasa, dan petunjuk', function () {
    $language = zhLanguage();

    $response = $this->actingAs(teacher())->get(route('manage.words.template', ['bahasa' => $language->id]))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('template-bank-kosakata-mandarin.xlsx');

    $book = IOFactory::load($response->getFile()->getPathname());
    expect($book->getSheetNames())->toBe(['Kosakata', 'Petunjuk'])
        ->and($book->getSheet(0)->rangeToArray('A1:H1')[0])->toBe(VocabularyTemplateExport::HEADINGS)
        ->and($book->getSheet(0)->getCell('A2')->getValue())->toBe('医生')
        ->and($book->getSheet(0)->getCell('B2')->getValue())->toBe('yīshēng');

    $this->actingAs(participant())->get(route('manage.words.template'))->assertForbidden();
});

test('import dari templat: baris contoh masuk dan lembar Petunjuk diabaikan', function () {
    $language = zhLanguage();
    $file = UploadedFile::fake()->createWithContent('kosakata.xlsx', Excel::raw(new VocabularyTemplateExport($language), \Maatwebsite\Excel\Excel::XLSX));

    Livewire::actingAs(teacher())->test('pages::manage.words')
        ->call('openImport')
        ->set('importLanguageId', $language->id)
        ->set('importFile', $file)
        ->call('import')->assertHasNoErrors()
        ->assertSet('importResult', ['created' => 3, 'updated' => 0, 'errors' => []])
        ->assertSee('3 kata baru, 0 diperbarui');

    expect(VocabularyWord::query()->where('language_id', $language->id)->orderBy('sort_order')->pluck('word')->all())->toBe(['医生', '护士', '谢谢']);
});

test('import CSV memperbarui kata yang ada, menambah kata baru, dan melaporkan baris salah', function () {
    $language = zhLanguage();
    VocabularyWord::factory()->create(['language_id' => $language->id, 'word' => '医生', 'meaning' => 'Dokter', 'sort_order' => 5]);
    $csv = "kata,cara_baca,arti,topik,urutan,aktif\n医生,yīshēng,Dokter (umum),Kesehatan,,\n病床,bìngchuáng,Tempat tidur pasien,Kesehatan,,tidak\n空白,kòngbái,,,,\n";

    Livewire::actingAs(programAdmin())->test('pages::manage.words')
        ->call('openImport')
        ->set('importLanguageId', $language->id)
        ->set('importFile', UploadedFile::fake()->createWithContent('kosakata.csv', $csv))
        ->call('import')->assertHasNoErrors()
        ->assertSet('importResult.created', 1)
        ->assertSet('importResult.updated', 1)
        ->assertSee('Baris 4');

    expect(VocabularyWord::query()->where('word', '医生')->sole())->meaning->toBe('Dokter (umum)')->sort_order->toBe(5)
        ->and(VocabularyWord::query()->where('word', '病床')->sole())->is_active->toBeFalse()->sort_order->toBe(6)
        ->and(VocabularyWord::query()->where('word', '空白')->exists())->toBeFalse();

    // Berkas wajib & jenisnya dibatasi.
    Livewire::actingAs(programAdmin())->test('pages::manage.words')
        ->call('openImport')->call('import')->assertHasErrors('importFile')
        ->set('importFile', UploadedFile::fake()->create('foto.png', 10, 'image/png'))->call('import')->assertHasErrors('importFile');
});
