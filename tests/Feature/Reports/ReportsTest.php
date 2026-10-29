<?php

use App\Actions\Assessments\SaveSessionScore;
use App\Enums\AttemptStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use App\Enums\SessionStatus;
use App\Enums\StaffUnit;
use App\Exports\ReportExport;
use App\Models\Assessment;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SessionAssessmentComponent;
use App\Models\Vendor;
use App\Services\Reports\ReportFilter;
use App\Services\Reports\ScoreAnalytics;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

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
        ->assertSee('2 Nov – 8 Nov 2026')
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
        ->assertSee('November 2026')
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
    $report = app(\App\Services\Reports\ReportBuilder::class)->build(\App\Enums\ReportType::MealReceipts, \App\Enums\ReportPeriod::Weekly, \Carbon\CarbonImmutable::parse('2026-11-04'), $this->batch->id, null);
    $binary = Excel::raw(new ReportExport($report, ['Angkatan uji']), \Maatwebsite\Excel\Excel::XLSX);

    $path = tempnam(sys_get_temp_dir(), 'lap').'.xlsx';
    file_put_contents($path, $binary);
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    unlink($path);

    expect($book->getSheetNames())->toBe(['Per jadwal', 'Per peserta', 'Per vendor', 'Rincian tanda terima'])
        ->and($book->getSheet(0)->getCell('A3')->getValue())->toBe('Cakupan: Angkatan uji')
        ->and($book->getSheet(2)->getCell('A7')->getValue())->toBe('Dapur Woku Tinoor');
});
