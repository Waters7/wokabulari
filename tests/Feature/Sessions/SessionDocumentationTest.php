<?php

use App\Actions\Sessions\EndSession;
use App\Actions\Sessions\ManageSessionCode;
use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
    app(SettingService::class)->setMany(['session.documentation_required' => true, 'session.documentation_from' => '2026-10-31']);
    $this->classroom = Classroom::factory()->withParticipants(1)->create();
});

function docSession(Classroom $classroom, string $date): LearningSession
{
    return LearningSession::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id, 'date' => $date]);
}

test('sesi mulai 31 Okt 2026 wajib dokumentasi saat mulai dan akhir; sesi sebelumnya tidak', function () {
    $old = docSession($this->classroom, '2026-10-30');
    $new = docSession($this->classroom, '2026-10-31');
    $codes = app(ManageSessionCode::class);

    $codes->start($old, $this->classroom->teacher);
    app(EndSession::class)->handle($old->fresh(), $this->classroom->teacher);
    expect($old->fresh()->status)->toBe(SessionStatus::Completed);

    expect(fn () => $codes->start($new, $this->classroom->teacher))->toThrow(InvalidArgumentException::class, 'sebelum memulai sesi');
    SessionDocumentation::factory()->for($new, 'session')->create();
    $codes->start($new->fresh(), $this->classroom->teacher);

    expect(fn () => app(EndSession::class)->handle($new->fresh(), $this->classroom->teacher))->toThrow(InvalidArgumentException::class, 'akhir sesi');
    SessionDocumentation::factory()->for($new, 'session')->end()->create();
    app(EndSession::class)->handle($new->fresh(), $this->classroom->teacher);
    expect($new->fresh()->status)->toBe(SessionStatus::Completed);
});

test('pengaturan bisa mematikan kewajiban dokumentasi', function () {
    app(SettingService::class)->setMany(['session.documentation_required' => false]);
    $session = docSession($this->classroom, '2026-11-05');

    app(ManageSessionCode::class)->start($session, $this->classroom->teacher);

    expect($session->fresh()->status)->toBe(SessionStatus::Ongoing);
});

test('mulai sesi membuka dialog dokumentasi; foto kamera tersimpan di disk private lalu sesi dimulai', function () {
    $session = docSession($this->classroom, '2026-11-02');

    $page = Livewire::actingAs($this->classroom->teacher)->test('pages::teach.session-show', ['session' => $session])
        ->call('start')
        ->assertSet('showDoc', true)
        ->assertSet('docPhase', 'start')
        ->call('submitDoc')
        ->assertHasErrors('docFile');
    expect($session->fresh()->status)->toBe(SessionStatus::Scheduled);

    $page->set('docFile', UploadedFile::fake()->image('kelas.jpg', 800, 600))
        ->call('submitDoc')
        ->assertHasNoErrors()
        ->assertSet('showDoc', false);

    $doc = $session->documentations()->first();
    expect($session->fresh()->status)->toBe(SessionStatus::Ongoing)
        ->and($doc->phase)->toBe(SessionPhase::Start)
        ->and($doc->source)->toBe(DocumentationSource::Camera)
        ->and($doc->uploaded_by)->toBe($this->classroom->teacher_id);
    Storage::disk('private')->assertExists($doc->path);
});

test('akhir sesi dengan unggah file PDF; file selain foto/PDF ditolak', function () {
    $session = docSession($this->classroom, '2026-11-02');
    SessionDocumentation::factory()->for($session, 'session')->create();
    app(ManageSessionCode::class)->start($session, $this->classroom->teacher);

    $page = Livewire::actingAs($this->classroom->teacher)->test('pages::teach.session-show', ['session' => $session->fresh()])
        ->call('end')
        ->assertSet('docPhase', 'end')
        ->assertSet('docThenAct', true)
        ->set('docSource', 'upload')
        ->set('docFile', UploadedFile::fake()->create('catatan.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->call('submitDoc')
        ->assertHasErrors('docFile');
    expect($session->fresh()->status)->toBe(SessionStatus::Ongoing);

    $page->set('docFile', UploadedFile::fake()->create('dokumentasi.pdf', 300, 'application/pdf'))
        ->call('submitDoc')
        ->assertHasNoErrors();

    $doc = $session->documentations()->where('phase', SessionPhase::End)->first();
    expect($session->fresh()->status)->toBe(SessionStatus::Completed)
        ->and($doc->source)->toBe(DocumentationSource::Upload)
        ->and($doc->original_name)->toBe('dokumentasi.pdf');
});

test('dokumentasi hanya dilihat pengajar kelas, admin, dan pimpinan; dokumentasi terakhir per tahap tidak bisa dihapus', function () {
    $session = docSession($this->classroom, '2026-11-02');
    $doc = SessionDocumentation::factory()->for($session, 'session')->create();
    $participant = $this->classroom->participants->first();

    expect($this->classroom->teacher->can('view', $doc))->toBeTrue()
        ->and(programAdmin()->can('view', $doc))->toBeTrue()
        ->and(leader()->can('view', $doc))->toBeTrue()
        ->and($participant->can('view', $doc))->toBeFalse()
        ->and(teacher()->can('view', $doc))->toBeFalse()
        ->and($this->classroom->teacher->can('delete', $doc))->toBeFalse();

    $second = SessionDocumentation::factory()->for($session, 'session')->create();
    expect($this->classroom->teacher->can('delete', $second))->toBeTrue()
        ->and(leader()->can('delete', $second))->toBeFalse();
});
