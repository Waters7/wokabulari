<?php

use App\Actions\Assessments\SaveSessionScore;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DeliveryUnit;
use App\Enums\DocumentationSource;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\HealthReportStatus;
use App\Enums\MealType;
use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Enums\SessionStatus;
use App\Enums\StaffUnit;
use App\Exports\AllReportsExport;
use App\Exports\ReportExport;
use App\Exports\ReportSheet;
use App\Models\Assessment;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\DormExitPermit;
use App\Models\HealthReport;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SessionAssessmentComponent;
use App\Models\SessionDocumentation;
use App\Models\Vendor;
use App\Services\Reports\MealReceiptRoll;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportFilter;
use App\Services\Reports\ScoreAnalytics;
use App\Support\Letterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Rabu 4 Nov 2026. Angkatan absen Senin–Jumat mulai Senin 2 Nov.
 * A: hadir 3 hari; B: terlambat, sakit, belum; C: tidak, hadir, belum.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-04 10:00', 'Asia/Makassar'));

    $this->batch = Batch::factory()->create(['start_date' => '2026-11-02', 'end_date' => '2027-01-29', 'attendance_days' => [1, 2, 3, 4, 5], 'attendance_target' => 80, 'passing_score' => 70]);
    $this->classroom = Classroom::factory()->for($this->batch)->withParticipants(3)->create(['name' => 'Kelas Inggris A']);
    [$this->a, $this->b, $this->c] = $this->classroom->participants()->orderBy('users.id')->get()->all();
    $this->a->update(['name' => 'Angel Rondonuwu']);
    $this->b->update(['name' => 'Brian Lumowa']);
    $this->c->update(['name' => 'Cindy Sondakh']);

    foreach ([
        [$this->a, '2026-11-02', AttendanceStatus::Present], [$this->a, '2026-11-03', AttendanceStatus::Present], [$this->a, '2026-11-04', AttendanceStatus::Present],
        [$this->b, '2026-11-02', AttendanceStatus::Late], [$this->b, '2026-11-03', AttendanceStatus::Sick],
        [$this->c, '2026-11-03', AttendanceStatus::Present],
    ] as [$user, $date, $status]) {
        DailyAttendance::factory()->create(['user_id' => $user->id, 'date' => $date, 'status' => $status, 'checked_in_at' => Carbon::parse($date.' 07:10')]);
    }

    // Sesi Selasa: A hadir, B alpa, C tidak tercatat; nilai sesi A 90, B 60.
    $this->session = LearningSession::factory()->for($this->classroom)->create(['date' => '2026-11-03', 'topic' => 'Perkenalan Diri', 'status' => SessionStatus::Completed]);
    $this->session->attendances()->create(['user_id' => $this->a->id, 'status' => AttendanceStatus::Present, 'submitted_at' => Carbon::parse('2026-11-03 08:05')]);
    $this->session->attendances()->create(['user_id' => $this->b->id, 'status' => AttendanceStatus::Absent]);
    $component = SessionAssessmentComponent::factory()->for($this->session, 'session')->create(['max_score' => 100]);
    app(SaveSessionScore::class)->handle($component, $this->a, '90');
    app(SaveSessionScore::class)->handle($component, $this->b, '60');

    // Quiz A 80%, penilaian mingguan A rata-rata 79,14.
    $quiz = Quiz::factory()->create(['classroom_id' => $this->classroom->id]);
    QuizAttempt::query()->create(['quiz_id' => $quiz->id, 'user_id' => $this->a->id, 'started_at' => '2026-11-03 09:00', 'expires_at' => '2026-11-03 09:30', 'submitted_at' => '2026-11-03 09:20', 'percentage' => 80, 'status' => AttemptStatus::Graded]);
    Assessment::factory()->for($this->classroom)->for($this->a)->published()->create(['period_start' => '2026-11-02', 'period_end' => '2026-11-08']);

    // Makan siang Selasa: 3 dos dari vendor, A tanda tangan sendiri, B diwakilkan.
    $this->vendor = Vendor::factory()->create(['name' => 'Dapur Woku Tinoor']);
    $this->meal = MealSchedule::factory()->create(['date' => '2026-11-03', 'meal_type' => MealType::Lunch, 'vendor_id' => $this->vendor->id, 'portions_ordered' => 3,
        'starts_at' => '2026-11-03 12:00', 'ends_at' => '2026-11-03 14:00']);
    MealDelivery::factory()->create(['meal_schedule_id' => $this->meal->id, 'vendor_id' => $this->vendor->id, 'quantity' => 3, 'unit' => DeliveryUnit::Box, 'location' => 'Ruang makan', 'ready_at' => '2026-11-03 12:00']);
    $this->meal->receipts()->create(['user_id' => $this->a->id, 'signature_path' => 'a.png', 'received_at' => '2026-11-03 12:10']);
    $this->meal->receipts()->create(['user_id' => $this->b->id, 'signature_path' => 'b.png', 'received_at' => '2026-11-03 12:30', 'proxy_by' => staff()->id, 'note' => 'Sakit di kamar']);
});

afterEach(fn () => Carbon::setTestNow());

function reportPage(array $params = [])
{
    return Livewire::actingAs(programAdmin())->withQueryParams($params)->test('pages::reports.index');
}

test('absen harian: siapa saja hadir hari ini dan unduh Excel', function () {
    Excel::fake();

    reportPage(['jenis' => 'daily_attendance', 'periode' => 'daily', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Rabu, 4 November 2026')
        ->assertSeeInOrder(['Angel Rondonuwu', 'Hadir', '07.10'])
        ->assertSeeInOrder(['Brian Lumowa', 'Belum absen'])
        ->call('export');

    Excel::assertDownloaded('absen-harian-2026-11-04-harian.xlsx', function (ReportExport $export) {
        $rows = $export->sheets()[0]->array();

        return str_contains($rows[1][0], '4 November 2026') && $rows[5][1] === 'Nama' && $rows[6][1] === 'Angel Rondonuwu' && $rows[6][4] === 'Hadir';
    });
});

test('rekap absen mingguan per peserta dan per hari', function () {
    reportPage(['jenis' => 'daily_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('2 – 4 November 2026')
        ->assertSee('55,6%')          // 5 dari 9 kewajiban absen
        ->assertSeeInOrder(['Angel Rondonuwu', '100,0'])
        ->assertSeeInOrder(['Brian Lumowa', '33,3'])
        ->assertSee('Rekap per hari');
});

test('absen per sesi menampilkan rekap sesi dan rincian peserta', function () {
    reportPage(['jenis' => 'session_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSeeInOrder(['Perkenalan Diri', '3', '1', '0', '0', '0', '1', '1', '33,3'])
        ->assertSeeInOrder(['Rincian peserta', 'Angel Rondonuwu', 'Hadir'])
        ->assertSee('Tidak tercatat');
});

test('laporan konsumsi diterima & input konsumsi; tim konsumsi hanya melihat laporan konsumsi', function () {
    $consumption = staff([StaffUnit::Consumption]);

    Livewire::actingAs($consumption)->withQueryParams(['jenis' => 'meal_receipts', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->test('pages::reports.index')
        ->assertSee('Konsumsi diterima')->assertDontSee('Absen harian')
        ->assertSeeInOrder(['Makan siang', 'Dapur Woku Tinoor', '3', '3 dos', '3', '2', '1', '66,7'])
        ->assertSeeInOrder(['Rincian tanda terima', 'Brian Lumowa', '12.30', 'Sakit di kamar'])
        ->set('type', 'meal_deliveries')
        ->assertSeeInOrder(['Input konsumsi', 'Dapur Woku Tinoor', '3', 'dos'])
        ->assertSeeInOrder(['Per vendor', 'Dapur Woku Tinoor', '1', '0', '3', '2']);

    // Meminta jenis laporan yang tidak diizinkan lewat URL dialihkan ke laporan yang boleh.
    Livewire::actingAs($consumption)->withQueryParams(['jenis' => 'scores'])->test('pages::reports.index')->assertSet('type', 'meal_receipts');

    $this->actingAs(staff([StaffUnit::Dormitory]))->get(route('reports.index'))->assertForbidden();
    $this->actingAs(participant())->get(route('reports.index'))->assertForbidden();
});

test('rekap nilai mingguan: rata-rata sesi, tugas, quiz, periodik, dan predikat', function () {
    $rows = app(ScoreAnalytics::class)->perParticipant(ReportFilter::make('2026-11-02', '2026-11-08', $this->batch->id))->keyBy(fn ($r) => $r['user']->id);

    expect($rows[$this->a->id])->session->toBe(90.0)->quiz->toBe(80.0)->periodic->toBe(79.14)->overall->toBe(83.05)->grade->toBe('B')
        ->and($rows[$this->b->id])->overall->toBe(60.0)->grade->toBe('D')
        ->and($rows[$this->c->id]['overall'])->toBeNull();

    reportPage(['jenis' => 'scores', 'periode' => 'monthly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('1 – 4 November 2026')
        ->assertSeeInOrder(['Angel Rondonuwu', '90,0', '–', '80,0', '79,1', '83,1', 'B'])
        ->assertSee('Per minggu')
        ->assertSeeInOrder(['Di bawah 70', '1']);
});

test('dashboard eksekutif: angka utama, catatan keputusan, perlu perhatian, dan drill-down peserta', function () {
    $page = Livewire::actingAs(leader())
        ->withQueryParams(['angkatan' => $this->batch->id, 'dari' => '2026-11-02', 'sampai' => '2026-11-04'])
        ->test('pages::leader.dashboard')
        ->assertSee('Dashboard eksekutif')
        ->assertSee('55,6%')
        ->assertSee('Kehadiran harian rata-rata 55,6% (target 80%).')
        ->assertSee('Serapan terendah: Makan siang (66,7%)')
        ->assertSeeInOrder(['Perlu perhatian', 'Brian Lumowa'])
        ->call('showParticipant', $this->a->id)
        ->assertSee('data-test="exec-participant"', false)
        ->assertSeeInOrder(['Angel Rondonuwu', 'Kehadiran harian', '100,0%', 'Rata-rata nilai', '83,1 (B)']);

    $this->actingAs(programAdmin())->get(route('leader.dashboard'))->assertOk();
    $this->actingAs(teacher())->get(route('leader.dashboard'))->assertForbidden();
    $this->actingAs(participant())->get(route('leader.dashboard'))->assertForbidden();
});

test('berkas Excel sungguhan terbentuk dengan satu lembar per tabel', function () {
    $report = app(ReportBuilder::class)->build(ReportType::MealReceipts, ReportPeriod::Weekly, CarbonImmutable::parse('2026-11-04'), $this->batch->id, null);
    $binary = Excel::raw(new ReportExport($report, ['Angkatan uji']), Maatwebsite\Excel\Excel::XLSX);

    $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($path, $binary);
    $book = IOFactory::load($path);
    unlink($path);

    expect($book->getSheetNames())->toBe(['Per jadwal', 'Per penerima', 'Per vendor', 'Rincian tanda terima', 'Tidak menerima'])
        ->and($book->getSheet(0)->getCell('A3')->getValue())->toBe('Cakupan: Angkatan uji')
        ->and($book->getSheet(2)->getCell('A7')->getValue())->toBe('Dapur Woku Tinoor');
});

test('dashboard eksekutif: tab grafik, urut kolom & filter tabel peserta', function () {
    $page = Livewire::actingAs(leader())
        ->withQueryParams(['angkatan' => $this->batch->id, 'dari' => '2026-11-02', 'sampai' => '2026-11-04'])
        ->test('pages::leader.dashboard')
        ->assertSee('data-test="exec-kpis"', false)
        ->call('setTab', 'kehadiran')->assertSee('Kehadiran harian (%)')->assertDontSee('data-test="exec-kpis"', false)
        ->call('setTab', 'nilai')->assertSee('Tren rata-rata nilai per minggu')
        ->call('setTab', 'konsumsi')->assertSee('Dapur Woku Tinoor')
        ->call('setTab', 'peserta')->assertSee('3 dari 3 peserta');

    // Urut nilai: terendah dulu, nilai kosong selalu di akhir; klik lagi membalik arah.
    $page->call('sortBy', 'score')->assertSet('direction', 'asc')
        ->assertSeeInOrder(['Brian Lumowa', 'Angel Rondonuwu', 'Cindy Sondakh'])
        ->call('sortBy', 'score')->assertSet('direction', 'desc')
        ->assertSeeInOrder(['Angel Rondonuwu', 'Brian Lumowa', 'Cindy Sondakh']);

    $page->set('show', 'low_score')->assertSee('1 dari 3 peserta')->assertSee('Brian Lumowa')->assertDontSee('Angel Rondonuwu')
        ->call('resetPeopleFilters')->set('grade', 'none')->assertSee('Cindy Sondakh')->assertDontSee('Brian Lumowa')
        ->call('resetPeopleFilters')->set('search', 'angel')->assertSee('1 dari 3 peserta');

    // Dari ringkasan "Lihat semua" membuka tab Peserta dengan filter perlu perhatian.
    $page->call('showPeople', 'attention')->assertSet('tab', 'peserta')->assertSet('show', 'attention');
});

test('absen per sesi: galeri dokumentasi foto per sesi, filter belum lengkap, kolom dokumentasi di Excel', function () {
    Storage::fake('private');
    Excel::fake();
    $complete = LearningSession::factory()->for($this->classroom)->create(['date' => '2026-11-04', 'topic' => 'Angka dan Waktu', 'status' => SessionStatus::Completed]);
    SessionDocumentation::factory()->for($complete, 'session')->create();
    SessionDocumentation::factory()->for($complete, 'session')->end()->create([
        'mime_type' => 'application/pdf', 'source' => DocumentationSource::Upload, 'original_name' => 'akhir.pdf',
    ]);
    $params = ['jenis' => 'session_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id];

    Livewire::actingAs(leader())->withQueryParams($params)->test('pages::reports.index')
        ->assertSee('Dokumentasi foto per sesi')
        ->assertSee('Belum lengkap (1)')
        ->assertSee('Dokumentasi belum lengkap')
        ->assertSeeHtml('wire:key="g-'.$complete->id.'"')
        ->assertSeeHtml('wire:key="g-'.$this->session->id.'"')
        ->assertSeeInOrder(['Perkenalan Diri', 'Mulai sesi', 'Belum ada', 'Akhir sesi', 'Belum ada'])
        ->assertSeeHtml(route('teach.sessions.show', $this->session))
        ->set('docFilter', 'missing')
        ->assertDontSeeHtml('wire:key="g-'.$complete->id.'"')
        ->assertSeeHtml('wire:key="g-'.$this->session->id.'"')
        ->call('export');

    Excel::assertDownloaded('absen-per-sesi-2026-11-02-sd-2026-11-04.xlsx', function (ReportExport $export) use ($complete) {
        $rows = $export->sheets()[0]->array();

        return $rows[5][13] === 'Dok. mulai' && $rows[5][15] === 'Tautan sesi'
            && $rows[6][13] === 'Belum ada' && $rows[6][14] === 'Belum ada'
            && $rows[7][13] === 'Ada (1)' && $rows[7][15] === route('teach.sessions.show', $complete);
    });
});

test('kolom tautan sesi di Excel menjadi tautan "Buka sesi" yang bisa diklik', function () {
    $report = app(ReportBuilder::class)->build(ReportType::SessionAttendance, ReportPeriod::Weekly, Carbon::parse('2026-11-04')->toImmutable(), $this->batch->id, null);
    $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($path, Excel::raw(new ReportExport($report), Maatwebsite\Excel\Excel::XLSX));

    $cell = IOFactory::load($path)->getSheet(0)->getCell('P7');
    @unlink($path);

    expect($cell->getValue())->toBe('Buka sesi')
        ->and($cell->getHyperlink()->getUrl())->toBe(route('teach.sessions.show', $this->session));
});

test('unduhan Excel absen per sesi memuat lembar Dokumentasi berisi foto mulai & akhir sesi', function () {
    Storage::fake('private');
    $photo = 'sesi/'.$this->session->id.'/dokumentasi/mulai.jpg';
    Storage::disk('private')->put($photo, UploadedFile::fake()->image('mulai.jpg', 1200, 900)->getContent());
    $start = SessionDocumentation::factory()->for($this->session, 'session')->create(['path' => $photo]);
    SessionDocumentation::factory()->for($this->session, 'session')->end()->create([
        'path' => 'sesi/x/akhir.pdf', 'mime_type' => 'application/pdf', 'source' => DocumentationSource::Upload, 'original_name' => 'absen-kelas.pdf',
    ]);

    $report = app(ReportBuilder::class)->build(ReportType::SessionAttendance, ReportPeriod::Weekly, Carbon::parse('2026-11-04')->toImmutable(), $this->batch->id, null);
    $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($path, Excel::raw(new ReportExport($report), Maatwebsite\Excel\Excel::XLSX));
    $sheet = IOFactory::load($path)->getSheetByName('Dokumentasi');
    @unlink($path);

    $drawings = $sheet->getDrawingCollection();
    expect($sheet->getCell('F6')->getValue())->toBe('Foto mulai sesi')
        ->and($sheet->getCell('D7')->getValue())->toBe('Perkenalan Diri')
        ->and($sheet->getCell('G7')->getValue())->toBe('absen-kelas.pdf')
        ->and($sheet->getCell('I7')->getValue())->toBe('Buka sesi')
        ->and(count($drawings))->toBe(1)
        ->and($drawings[0]->getCoordinates())->toBe('F7')
        ->and($drawings[0]->getHeight())->toBe(ReportSheet::IMAGE_HEIGHT);

    // Gambar kecil dibuat sekali lalu dipakai ulang; ikut terhapus bersama dokumentasinya.
    Storage::disk('private')->assertExists($start->thumbnailFile());
    $start->delete();
    Storage::disk('private')->assertMissing($start->thumbnailFile());
});

test('lembar Dokumentasi hanya untuk unduhan, tidak tampil sebagai tabel di layar', function () {
    reportPage(['jenis' => 'session_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Per sesi')
        ->assertDontSee('Foto mulai sesi');
});

test('periode dipotong sampai tanggal yang dipilih; panah pindah ke akhir periode', function () {
    [$from, $to] = ReportPeriod::Monthly->until('2026-11-06');

    expect($from->toDateString())->toBe('2026-11-01')
        ->and($to->toDateString())->toBe('2026-11-06')
        ->and(ReportPeriod::Monthly->untilLabel('2026-11-06'))->toBe('1 – 6 November 2026')
        ->and(ReportPeriod::Monthly->untilLabel('2026-10-31'))->toBe('Oktober 2026')
        ->and(ReportPeriod::Weekly->until('2026-11-04')[0]->toDateString())->toBe('2026-11-02')
        ->and(ReportPeriod::Range->untilLabel('2026-11-06', '2026-10-28'))->toBe('28 Okt – 6 Nov 2026')
        ->and(ReportPeriod::Range->until('2026-10-28', '2026-11-06')[1]->toDateString())->toBe('2026-11-06')
        // Hari ini Rabu 4 Nov: bulan lalu sampai 31 Okt; kembali ke bulan berjalan sampai hari ini.
        ->and(ReportPeriod::Monthly->shiftUntil('2026-11-04', -1)->toDateString())->toBe('2026-10-31')
        ->and(ReportPeriod::Monthly->shiftUntil('2026-10-31', 1)->toDateString())->toBe('2026-11-04')
        ->and(ReportPeriod::Weekly->shiftUntil('2026-11-04', -1)->toDateString())->toBe('2026-11-01');

    // Input konsumsi Rabu 4 Nov tidak ikut bila laporan bulanan diminta sampai 3 Nov.
    $wednesday = MealSchedule::factory()->create(['date' => '2026-11-04', 'meal_type' => MealType::Dinner, 'vendor_id' => $this->vendor->id, 'portions_ordered' => 3,
        'starts_at' => '2026-11-04 18:00', 'ends_at' => '2026-11-04 20:00']);
    MealDelivery::factory()->create(['meal_schedule_id' => $wednesday->id, 'vendor_id' => $this->vendor->id, 'quantity' => 7, 'unit' => DeliveryUnit::Box, 'location' => 'Aula', 'ready_at' => '2026-11-04 18:00']);

    reportPage(['jenis' => 'meal_deliveries', 'periode' => 'monthly', 'tanggal' => '2026-11-03'])
        ->assertSee('1 – 3 November 2026')
        ->assertSee('Data diambil sampai 3 Nov 2026')
        ->assertSee('Sel, 3 Nov 2026')
        ->assertDontSee('Rab, 4 Nov 2026')
        ->set('date', '2026-11-04')
        ->assertSee('Rab, 4 Nov 2026');

    reportPage(['jenis' => 'daily_attendance', 'periode' => 'monthly', 'tanggal' => '2026-11-03', 'angkatan' => $this->batch->id])
        ->assertSee('Sel, 3 Nov 2026')
        ->assertDontSee('Rab, 4 Nov 2026');
});

test('periode rentang bebas: dari–sampai, satu hari menampilkan absen harian', function () {
    reportPage(['jenis' => 'daily_attendance', 'periode' => 'range', 'dari' => '2026-11-02', 'tanggal' => '2026-11-03', 'angkatan' => $this->batch->id])
        ->assertSee('2 – 3 November 2026')
        ->assertSee('Rekap per hari')
        ->call('shift', 1)
        ->assertSet('from', '2026-11-04')
        ->assertSet('date', '2026-11-05')
        ->set('from', '2026-11-03')->set('date', '2026-11-03')
        ->assertSee('Selasa, 3 November 2026')
        ->assertSeeInOrder(['Brian Lumowa', 'Sakit']);
});

test('unduh semua laporan: satu Excel berisi ringkasan dan lembar tiap laporan sesuai hak akses', function () {
    Excel::fake();

    reportPage(['jenis' => 'daily_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Unduh semua laporan')
        ->call('exportAll');

    Excel::assertDownloaded('semua-laporan-2026-11-02-sd-2026-11-04.xlsx', function (AllReportsExport $export) {
        $sheets = $export->sheets();
        $titles = array_map(fn (ReportSheet $sheet) => $sheet->title(), $sheets);
        $summary = $sheets[0]->array();

        return $titles[0] === 'Ringkasan' && in_array(['Absen harian', 'Kehadiran rata-rata', '55,6%'], $summary, true)
            && in_array('Harian - Rekap per peserta', $titles, true) && in_array('Sesi - Dokumentasi', $titles, true)
            && in_array('Konsumsi - Rincian tanda terima', $titles, true) && in_array('Input - Foto konsumsi', $titles, true)
            && in_array('Nilai - Rekap nilai', $titles, true) && in_array('Mengajar - Rincian sesi', $titles, true)
            && count($titles) === count(array_unique($titles))
            && str_contains($sheets[1]->array()[1][0], '2 – 4 November 2026');
    });

    // Tim konsumsi: hanya laporan konsumsi, termasuk foto konsumsi, tanpa dokumentasi kelas.
    Livewire::actingAs(staff([StaffUnit::Consumption]))->withQueryParams(['jenis' => 'meal_receipts', 'periode' => 'weekly', 'tanggal' => '2026-11-04'])
        ->test('pages::reports.index')->call('exportAll');

    Excel::assertDownloaded('semua-laporan-2026-11-02-sd-2026-11-04.xlsx', function (AllReportsExport $export) {
        $titles = array_map(fn (ReportSheet $sheet) => $sheet->title(), $export->sheets());

        return in_array('Input - Foto konsumsi', $titles, true) && ! in_array('Sesi - Dokumentasi', $titles, true)
            && collect($titles)->every(fn ($t) => $t === 'Ringkasan' || str_starts_with($t, 'Konsumsi') || str_starts_with($t, 'Input'));
    });
});

test('foto dokumentasi konsumsi tampil di laporan input konsumsi dan tersemat di Excel', function () {
    Storage::fake('private');
    $photo = 'meal-deliveries/2026/11/menu-siang.jpg';
    Storage::disk('private')->put($photo, UploadedFile::fake()->image('menu.jpg', 1200, 900)->getContent());
    $this->meal->delivery->update(['menu_photo_path' => $photo]);

    reportPage(['jenis' => 'meal_deliveries', 'periode' => 'weekly', 'tanggal' => '2026-11-04'])
        ->assertSee('Foto dokumentasi konsumsi')
        ->assertSee('Foto belum lengkap (1)')
        ->assertSeeHtml('wire:key="mg-'.$this->meal->id.'"')
        ->assertSeeInOrder(['Foto menu', 'Foto total', 'Belum ada'])
        ->assertSeeInOrder(['Input tanpa foto', '0']);

    $report = app(ReportBuilder::class)->between(ReportType::MealDeliveries, CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-04'), null, null);
    $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($path, Excel::raw(new AllReportsExport([['type' => ReportType::MealDeliveries, 'report' => $report, 'scope' => []]], $report['period']), Maatwebsite\Excel\Excel::XLSX));
    $sheet = IOFactory::load($path)->getSheetByName('Input - Foto konsumsi');
    @unlink($path);

    expect($sheet->getCell('F6')->getValue())->toBe('Foto menu')
        ->and($sheet->getCell('G7')->getValue())->toBe('Belum ada')
        ->and(count($sheet->getDrawingCollection()))->toBe(1)
        ->and($sheet->getDrawingCollection()[0]->getCoordinates())->toBe('F7');
    Storage::disk('private')->assertExists(MealDelivery::thumbnailFile($photo));
});

test('unduh PDF berkop: kop Poltekkes, periode, cakupan, dan waktu cetak di setiap halaman', function () {
    reportPage(['jenis' => 'daily_attendance', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Unduh PDF berkop')
        ->call('exportPdf')
        ->assertFileDownloaded('absen-harian-2026-11-02-sd-2026-11-04.pdf');

    $report = app(ReportBuilder::class)->between(ReportType::DailyAttendance, CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-04'), $this->batch->id, null);
    $data = ['report' => $report, 'scope' => ['Angkatan uji'], 'kop' => Letterhead::pdf(), 'printedAt' => now(), 'printedBy' => 'Admin Program'];
    $html = view('pdf.report', $data)->render();

    expect($html)->toContain('data:image/png;base64,')
        ->toContain('Dicetak: Rabu, 4 November 2026 pukul 10.00 WITA oleh Admin Program')
        ->toContain('2 – 4 November 2026')
        ->toContain('Angel Rondonuwu')
        ->and(Letterhead::output(Pdf::loadView('pdf.report', $data)->setPaper('a4', 'landscape')))->toStartWith('%PDF');
});

test('konsumsi diterima: yang tidak menerima dicantumkan beserta alasan sesuai tanggal & jam jadwal', function () {
    $schedule = fn (string $date, MealType $type, string $start, string $end) => MealSchedule::factory()->create([
        'date' => $date, 'meal_type' => $type, 'vendor_id' => $this->vendor->id, 'portions_ordered' => 3, 'starts_at' => "$date $start", 'ends_at' => "$date $end",
    ]);
    $breakfast = $schedule('2026-11-02', MealType::Breakfast, '06:30', '08:00');
    $dinner = $schedule('2026-11-03', MealType::Dinner, '18:00', '19:30');
    $today = $schedule('2026-11-04', MealType::Lunch, '12:00', '14:00');

    // Angel pulang sore Selasa (scan keluar 17.05, kembali 20.50): hanya makan malam yang terlewat.
    DormExitPermit::factory()->create(['user_id' => $this->a->id, 'type' => ExitPermitType::HomeVisit, 'status' => ExitPermitStatus::Returned,
        'planned_departure' => '2026-11-03 17:00', 'planned_return' => '2026-11-03 21:00', 'actual_departure' => '2026-11-03 17:05', 'actual_return' => '2026-11-03 20:50']);
    // Cindy izin kelas Selasa; alpa di absen harian Senin. Laporan sakitnya sudah sembuh sebelum Selasa.
    LeaveRequest::factory()->approved()->create(['user_id' => $this->c->id, 'date_from' => '2026-11-03', 'date_to' => '2026-11-03']);
    DailyAttendance::factory()->create(['user_id' => $this->c->id, 'date' => '2026-11-02', 'status' => AttendanceStatus::Absent, 'checked_in_at' => null]);
    HealthReport::factory()->create(['user_id' => $this->c->id, 'reported_at' => '2026-11-01 08:00', 'status' => HealthReportStatus::Recovered, 'resolved_at' => '2026-11-02 05:00']);

    $items = app(MealReceiptRoll::class)->build(ReportFilter::make('2026-11-02', '2026-11-04', $this->batch->id));
    $reasons = fn (MealSchedule $s, $user) => collect($items->firstWhere('schedule.id', $s->id)['rows'])->firstWhere('user.id', $user->id)['reasons'];

    expect($reasons($this->meal, $this->a))->toBe([])
        ->and($reasons($this->meal, $this->c))->toBe(['Izin'])
        ->and($reasons($dinner, $this->a))->toBe(['Izin keluar asrama (Pulang ke rumah)'])
        ->and($reasons($dinner, $this->b))->toBe(['Sakit'])
        ->and($reasons($dinner, $this->c))->toBe(['Izin'])
        ->and($reasons($breakfast, $this->a))->toBe(['Tidak mengambil (tanpa keterangan)'])
        ->and($reasons($breakfast, $this->c))->toBe(['Alpa di absen harian'])
        ->and($reasons($today, $this->a))->toBe(['Belum mengambil (jadwal belum selesai)'])
        ->and($items->firstWhere('schedule.id', $dinner->id)['missed'])->toBe(3);

    reportPage(['jenis' => 'meal_receipts', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Tidak menerima')
        ->assertSeeInOrder(['Tidak menerima', 'Makan malam', 'Angel Rondonuwu', 'Kelas Inggris A', 'Izin keluar asrama (Pulang ke rumah)']);
});

test('unduh tanda terima + TTD: PDF per jadwal berkop dengan tanda tangan; lebih dari 7 hari jadi ZIP per minggu', function () {
    Storage::fake('private');
    $signature = 'signatures/2026/11/angel.png';
    Storage::disk('private')->put($signature, UploadedFile::fake()->image('ttd.png', 600, 240)->getContent());
    $this->meal->receipts()->where('user_id', $this->a->id)->update(['signature_path' => $signature]);

    reportPage(['jenis' => 'meal_receipts', 'periode' => 'weekly', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->assertSee('Tanda terima + TTD (PDF)')
        ->call('exportReceipts')
        ->assertFileDownloaded('tanda-terima-konsumsi-2026-11-02-sd-2026-11-04.pdf');

    $thumb = 'signatures/2026/11/thumbs/angel.jpg';
    expect(Storage::disk('private')->exists($thumb))->toBeTrue();

    $items = app(MealReceiptRoll::class)->build(ReportFilter::make('2026-11-03', '2026-11-03', $this->batch->id));
    $html = view('pdf.meal-receipts', ['items' => $items, 'period' => '3 November 2026', 'scope' => ['Angkatan uji'], 'kop' => Letterhead::pdf(), 'printedAt' => now(), 'printedBy' => 'Admin Program'])->render();

    expect($html)->toContain('DAFTAR TANDA TERIMA KONSUMSI')
        ->toContain('Selasa, 3 November 2026')->toContain('Makan siang')->toContain('Dapur Woku Tinoor')
        ->toContain(e(Storage::disk('private')->path($thumb)))
        ->toContain('Diwakilkan oleh')->toContain('Sakit di kamar')
        ->toContain('Tidak mengambil (tanpa keterangan)')
        ->toContain('Penanggung jawab konsumsi')
        ->toContain('Dicetak: Rabu, 4 November 2026 pukul 10.00 WITA oleh Admin Program');

    // Rentang 10 hari: satu PDF per minggu (26 Okt–1 Nov, 2–4 Nov) dalam satu ZIP.
    $page = reportPage(['jenis' => 'meal_receipts', 'periode' => 'range', 'dari' => '2026-10-26', 'tanggal' => '2026-11-04', 'angkatan' => $this->batch->id])
        ->call('exportReceipts')
        ->assertFileDownloaded('tanda-terima-konsumsi-2026-10-26-sd-2026-11-04.zip');

    $path = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($path, base64_decode($page->effects['download']['content']));
    $zip = new ZipArchive;
    $zip->open($path);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
    $first = $zip->getFromIndex(1);
    $zip->close();
    unlink($path);

    expect($names)->toBe(['tanda-terima-konsumsi-2026-10-26-sd-2026-11-01.pdf', 'tanda-terima-konsumsi-2026-11-02-sd-2026-11-04.pdf'])
        ->and($first)->toStartWith('%PDF');
});

test('tanda terima + TTD juga untuk tim konsumsi; tombol hanya di laporan konsumsi diterima', function () {
    Livewire::actingAs(staff([StaffUnit::Consumption]))->withQueryParams(['jenis' => 'meal_receipts', 'periode' => 'daily', 'tanggal' => '2026-11-03'])
        ->test('pages::reports.index')
        ->call('exportReceipts')
        ->assertFileDownloaded('tanda-terima-konsumsi-2026-11-03-harian.pdf');

    reportPage(['jenis' => 'daily_attendance'])->assertDontSee('Tanda terima + TTD (PDF)');
});
