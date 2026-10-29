<?php

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Enums\TeachingStatus;
use App\Exports\ReportExport;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Services\Reports\ReportFilter;
use App\Services\Reports\TeachingAnalytics;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 10:00', 'Asia/Makassar'));
    $this->classroom = Classroom::factory()->withParticipants(2)->create(['name' => 'Kelas Mandarin A']);
    $this->lead = $this->classroom->teacher;
    $this->lead->update(['name' => 'Lina Tan']);
    $this->co = teacher();
    $this->co->update(['name' => 'Markus Lumingkewas']);
    $this->classroom->syncTeachers($this->lead->id, [$this->co->id]);
    $this->p1 = $this->classroom->participants->first();

    $session = fn (string $topic, string $start, string $end) => LearningSession::factory()->for($this->classroom)->create([
        'teacher_id' => $this->lead->id, 'date' => '2026-11-03', 'start_time' => $start, 'end_time' => $end, 'topic' => $topic, 'status' => SessionStatus::Completed,
    ]);

    // A: pengajar utama membuat kode & ada peserta → hadir (2 jam).
    $this->a = $session('Perkenalan diri', '08:00', '10:00');
    $code = $this->a->codes()->create(['code' => 'ABC123', 'expires_at' => '2026-11-03 08:15', 'is_active' => false, 'generated_by' => $this->lead->id]);
    $code->forceFill(['created_at' => '2026-11-03 08:02'])->save();
    $this->a->attendances()->create(['user_id' => $this->p1->id, 'status' => AttendanceStatus::Present, 'submitted_at' => '2026-11-03 08:05']);

    // B: tidak ada kode → tidak tercatat.
    $this->b = $session('Angka dan waktu', '10:30', '12:00');

    // C: pengajar pendamping membuat kode tetapi belum ada peserta.
    $this->c = $session('Di rumah sakit', '13:00', '14:30');
    $this->c->codes()->create(['code' => 'XYZ789', 'expires_at' => '2026-11-03 13:15', 'is_active' => false, 'generated_by' => $this->co->id]);
});

afterEach(fn () => Carbon::setTestNow());

test('pengajar hadir bila membuat kode sesi dan ada peserta; jam mengajar dihitung', function () {
    $filter = ReportFilter::make('2026-11-02', '2026-11-08', $this->classroom->batch_id);
    $rows = app(TeachingAnalytics::class)->rows($filter);
    $status = fn ($session, $teacher) => $rows->first(fn ($r) => $r['session']->is($session) && $r['teacher']->is($teacher))['status'] ?? null;

    expect($status($this->a, $this->lead))->toBe(TeachingStatus::Present)
        ->and($status($this->b, $this->lead))->toBe(TeachingStatus::NotRecorded)
        ->and($status($this->c, $this->co))->toBe(TeachingStatus::NoParticipants)
        ->and($status($this->c, $this->lead))->toBe(TeachingStatus::NotRecorded);

    $summary = app(TeachingAnalytics::class)->perTeacher($rows)->keyBy(fn ($r) => $r['teacher']->id);
    expect($summary[$this->lead->id])->present->toBe(1)->not_recorded->toBe(2)->hours->toBe(2.0)
        ->and($summary[$this->co->id])->present->toBe(0)->no_participants->toBe(1)->hours->toBe(0.0);
});

test('laporan mengajar di Pusat Laporan dengan filter pengajar', function () {
    Livewire::actingAs(programAdmin())->withQueryParams(['jenis' => 'teaching', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->classroom->batch_id])
        ->test('pages::reports.index')
        ->assertSee('Laporan mengajar')
        ->assertSeeInOrder(['Rekap per pengajar', 'Lina Tan', 'Kelas Mandarin A', '3', '1', '0', '2', '2,0'])
        ->assertSeeInOrder(['Rincian sesi', 'Perkenalan diri', 'Lina Tan', 'Hadir', '08.02'])
        ->set('teacherId', $this->co->id)
        ->assertSee('Markus Lumingkewas')->assertSee('Kode dibuat, belum ada peserta')
        ->assertDontSee('Perkenalan diri');

    $this->actingAs(teacher())->get(route('reports.index', ['jenis' => 'teaching']))->assertForbidden();
});

test('pengajar melihat dan mengunduh laporan mengajarnya sendiri; status tampil di halaman sesi', function () {
    Excel::fake();

    Livewire::actingAs($this->co)->withQueryParams(['periode' => 'weekly', 'tanggal' => '2026-11-04'])->test('pages::teach.report')
        ->assertSee('Di rumah sakit')->assertSee('Kode dibuat, belum ada peserta')
        ->assertDontSee('Perkenalan diri')
        ->call('export');
    Excel::assertDownloaded('laporan-mengajar-2026-11-02.xlsx', fn (ReportExport $export) => count($export->sheets()) === 2);

    $this->actingAs($this->lead)->get(route('teach.sessions.show', $this->a))->assertSee('data-test="teaching-status"', false)->assertSee('sudah tercatat');
    $this->actingAs($this->co)->get(route('teach.sessions.show', $this->c))->assertSee('setelah peserta pertama masuk');
    $this->actingAs(participant())->get(route('teach.report'))->assertForbidden();
});
