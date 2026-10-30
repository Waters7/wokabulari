<?php

use App\Actions\Assessments\SaveAssessment;
use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Models\Assessment;
use App\Models\AssessmentAspect;
use App\Models\Classroom;
use App\Models\Language;
use App\Services\Gamification\BadgeService;
use App\Services\PeriodicAspects;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('migrasi menyiapkan tujuh aspek bawaan dan menyalin nilai kolom lama', function () {
    expect(AssessmentAspect::query()->where('is_default', true)->whereNotNull('code')->count())->toBe(7);

    // Penilaian lama yang masih tersimpan di kolom aspek ikut tersalin bila migrasi dijalankan ulang.
    $assessment = Assessment::factory()->create();
    DB::table('assessments')->where('id', $assessment->id)->update(['speaking' => 77, 'grammar' => 66]);
    $migration = require database_path('migrations/2026_11_04_002600_create_assessment_aspects.php');
    $migration->up();
    $migration->up();

    expect($assessment->fresh()->scoreFor('speaking'))->toBe(77.0)
        ->and($assessment->fresh()->scoreFor('grammar'))->toBe(66.0)
        ->and($assessment->fresh()->scores()->count())->toBe(2);
});

test('kelas tanpa pilihan memakai aspek bawaan; aspek khusus bahasa lain tidak muncul', function () {
    $classroom = Classroom::factory()->create();
    $other = Language::factory()->create();
    AssessmentAspect::factory()->create(['name' => 'Kanji', 'language_id' => $other->id]);
    $own = AssessmentAspect::factory()->create(['name' => 'Hanzi', 'language_id' => $classroom->language_id]);

    $aspects = app(PeriodicAspects::class);

    expect($aspects->selected($classroom)->pluck('code')->filter()->count())->toBe(7)
        ->and($aspects->available($classroom)->pluck('name'))->toContain('Hanzi')->not->toContain('Kanji')
        ->and($aspects->isCustomized($classroom))->toBeFalse();

    expect(fn () => app(SaveAssessment::class)->save($classroom->fresh(), Classroom::factory()->withParticipants(1)->create()->participants->first(), $classroom->teacher, PeriodType::Weekly, now(), ['scores' => [$own->id => 80]]))
        ->toThrow(InvalidArgumentException::class);
});

test('pengajar memilih aspek kelas; nilai aspek yang tidak dipilih lagi tetap tampil di periodenya', function () {
    $classroom = Classroom::factory()->withParticipants(2)->create();
    [$a, $b] = $classroom->participants()->orderBy('users.id')->get()->all();
    $hanzi = AssessmentAspect::factory()->create(['name' => 'Hanzi', 'language_id' => $classroom->language_id]);
    $speaking = AssessmentAspect::query()->where('code', 'speaking')->first();

    $page = Livewire::actingAs($classroom->teacher)->test('pages::teach.assessments')
        ->set('classroom', $classroom->id)
        ->call('openAspects')
        ->set('chosenAspects', [(string) $speaking->id, (string) $hanzi->id])
        ->call('saveAspects')
        ->assertHasNoErrors()
        ->assertSet('aspectIds', [$speaking->id, $hanzi->id])
        ->set("rows.{$a->id}.scores.{$hanzi->id}", '90')
        ->set("rows.{$a->id}.scores.{$speaking->id}", '80')
        ->call('saveDraft')
        ->assertHasNoErrors();

    // Baris kosong (peserta B) tidak dibuat sebagai draf.
    $assessment = Assessment::query()->where('user_id', $a->id)->first();
    expect($assessment->average)->toBe(85.0)
        ->and(Assessment::query()->where('user_id', $b->id)->exists())->toBeFalse();

    // Hanzi tidak dipilih lagi: kolomnya tetap ada di periode yang sudah berisi nilai Hanzi.
    $page->call('openAspects')->set('chosenAspects', [(string) $speaking->id])->call('saveAspects')
        ->assertSet('aspectIds', [$speaking->id, $hanzi->id]);
    expect($classroom->assessmentAspects()->pluck('assessment_aspects.id')->all())->toBe([$speaking->id]);

    // Minimal satu aspek; "pakai bawaan" mengosongkan pilihan.
    $page->call('openAspects')->set('chosenAspects', [])->call('saveAspects')->assertHasErrors('chosenAspects');
    $page->call('resetAspects');
    expect($classroom->assessmentAspects()->count())->toBe(0);
});

test('filter peserta berdasarkan nama dan status penilaian', function () {
    $classroom = Classroom::factory()->withParticipants(3)->create();
    [$a, $b, $c] = $classroom->participants()->orderBy('users.id')->get()->all();
    $a->update(['name' => 'Ani Lumowa']);
    $b->update(['name' => 'Budi Kalalo']);
    $c->update(['name' => 'Citra Mamuaja']);
    $speaking = AssessmentAspect::query()->where('code', 'speaking')->value('id');
    $save = app(SaveAssessment::class);
    $save->publish([$save->save($classroom, $a, $classroom->teacher, PeriodType::Weekly, now(), ['scores' => [$speaking => 80]])]);
    $save->save($classroom, $b, $classroom->teacher, PeriodType::Weekly, now(), ['scores' => [$speaking => 70]]);

    $page = Livewire::actingAs($classroom->teacher)->test('pages::teach.assessments')->set('classroom', $classroom->id);

    $page->set('status', 'published')->assertSee('Ani Lumowa')->assertDontSee('Budi Kalalo')->assertDontSee('Citra Mamuaja');
    $page->set('status', 'draft')->assertSee('Budi Kalalo')->assertDontSee('Ani Lumowa');
    $page->set('status', 'empty')->assertSee('Citra Mamuaja')->assertDontSee('Budi Kalalo');
    $page->set('status', '')->set('search', 'kalalo')->assertSee('Budi Kalalo')->assertDontSee('Citra Mamuaja');
    $page->call('resetFilters')->assertSee('Citra Mamuaja')->assertSee('2/3');
});

test('nilai bukan angka ditolak dan ditandai per peserta', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $speaking = AssessmentAspect::query()->where('code', 'speaking')->value('id');

    Livewire::actingAs($classroom->teacher)->test('pages::teach.assessments')
        ->set('classroom', $classroom->id)
        ->set("rows.{$participant->id}.scores.{$speaking}", 'abc')
        ->call('publish')
        ->assertHasErrors("rows.{$participant->id}");

    expect(Assessment::count())->toBe(0);
});

test('master aspek: admin program menambah & menonaktifkan; aspek bawaan atau berisi nilai tidak bisa dihapus', function () {
    $admin = programAdmin();

    Livewire::actingAs($admin)->test('pages::master.assessment-aspects')
        ->call('create')
        ->set('form.name', 'Pengucapan')
        ->set('form.source_aspect', LanguageAspect::Speaking->value)
        ->set('form.is_default', true)
        ->call('save')
        ->assertHasNoErrors();

    $aspect = AssessmentAspect::query()->where('name', 'Pengucapan')->first();
    expect($aspect->source_aspect)->toBe(LanguageAspect::Speaking)->and($aspect->language_id)->toBeNull()->and($aspect->is_default)->toBeTrue();

    // Nama sama untuk cakupan bahasa yang sama ditolak.
    Livewire::actingAs($admin)->test('pages::master.assessment-aspects')
        ->call('create')->set('form.name', 'Pengucapan')->call('save')->assertHasErrors('form.name');

    $builtIn = AssessmentAspect::query()->where('code', 'grammar')->first();
    Livewire::actingAs($admin)->test('pages::master.assessment-aspects')
        ->call('delete', $builtIn->id)
        ->call('toggleActive', $builtIn->id);
    expect($builtIn->fresh())->not->toBeNull()->and($builtIn->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)->test('pages::master.assessment-aspects')->call('delete', $aspect->id);
    expect(AssessmentAspect::query()->find($aspect->id))->toBeNull();

    $this->actingAs(teacher())->get(route('master.assessment-aspects'))->assertForbidden();
    $this->actingAs(leader())->get(route('master.assessment-aspects'))->assertForbidden();
    $this->actingAs($admin)->get(route('master.assessment-aspects'))->assertOk()->assertSee('Menyimak');
});

test('halaman nilai peserta & wali menampilkan nilai per aspek yang terbit', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    Assessment::factory()->for($classroom)->for($participant)->published()->withScores(['speaking' => 88, 'vocabulary' => 79, 'grammar' => 70])->create();

    $this->actingAs($participant)->get(route('grades.index'))->assertOk()->assertSee('Berbicara 88')->assertSee('Kosakata 79');
});

test('kriteria lencana aspek membaca nilai aspek dari penilaian terbit terakhir', function () {
    $this->seed(GamificationSeeder::class);
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $participant = $classroom->participants->first();
    $badges = app(BadgeService::class);

    Assessment::factory()->for($classroom)->for($participant)->published()->withScores(['speaking' => 80])->create(['period_end' => now()->subWeek()]);
    expect($badges->qualifies($participant, ['type' => 'aspect', 'aspect' => 'speaking', 'min' => 85]))->toBeFalse();

    Assessment::factory()->for($classroom)->for($participant)->published()->withScores(['speaking' => 90])->create(['period_start' => now()->addWeek()->startOfWeek(), 'period_end' => now()->addWeek()->endOfWeek()]);
    expect($badges->qualifies($participant, ['type' => 'aspect', 'aspect' => 'speaking', 'min' => 85]))->toBeTrue();
});
