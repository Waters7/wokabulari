<?php

namespace App\Services\Reports;

use App\Enums\MealType;
use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Models\Batch;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\GradeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Isi Pusat Laporan: ringkasan + satu atau beberapa tabel (lembar) yang sama untuk layar dan Excel.
 *
 * @phpstan-type Sheet array{title: string, headings: list<string>, rows: list<list<scalar|null>>}
 * @phpstan-type Report array{title: string, period: string, summary: list<array{label: string, value: string}>, sheets: list<Sheet>}
 */
class ReportBuilder
{
    public function __construct(
        protected AttendanceAnalytics $attendance,
        protected ScoreAnalytics $scores,
        protected MealAnalytics $meals,
    ) {}

    /**
     * @return Report
     */
    public function build(ReportType $type, ReportPeriod $period, CarbonImmutable $date, ?int $batchId, ?int $classroomId): array
    {
        [$from, $to] = $period->range($date);
        $filter = ReportFilter::make($from, $to, $batchId, $classroomId);

        $report = match ($type) {
            ReportType::DailyAttendance => $period === ReportPeriod::Daily ? $this->attendanceDay($filter) : $this->attendanceRecap($filter),
            ReportType::SessionAttendance => $this->sessionAttendance($filter),
            ReportType::MealReceipts => $this->mealReceipts($filter),
            ReportType::MealDeliveries => $this->mealDeliveries($filter),
            ReportType::Scores => $this->scoreRecap($filter, $period),
        };

        return ['title' => $type->label(), 'period' => $period->periodLabel($date)] + $report;
    }

    protected function attendanceDay(ReportFilter $filter): array
    {
        $date = $filter->from;
        $rows = $this->attendance->roster($filter, $date);
        $status = fn (array $row) => $row['record']?->status->label()
            ?? (! $row['scheduled'] ? 'Bukan hari absen' : ($date->isToday() ? 'Belum absen' : 'Tidak absen'));

        $scheduled = $rows->where('scheduled', true);
        $attended = $scheduled->filter(fn ($row) => $row['record']?->isAttended())->count();
        $count = fn (string $label) => (string) $scheduled->filter(fn ($row) => $status($row) === $label)->count();

        return [
            'summary' => [
                ['label' => 'Wajib absen', 'value' => (string) $scheduled->count()],
                ['label' => 'Hadir', 'value' => $count('Hadir')],
                ['label' => 'Terlambat', 'value' => $count('Terlambat')],
                ['label' => 'Izin / sakit', 'value' => (string) ((int) $count('Izin') + (int) $count('Sakit'))],
                ['label' => $date->isToday() ? 'Belum absen' : 'Tidak absen', 'value' => $count($date->isToday() ? 'Belum absen' : 'Tidak absen')],
                ['label' => 'Kehadiran', 'value' => $this->percent($scheduled->count() ? $attended / $scheduled->count() * 100 : null)],
            ],
            'sheets' => [[
                'title' => 'Absen harian',
                'headings' => ['No', 'Nama', 'NIS', 'Kelas', 'Status', 'Jam absen', 'Catatan'],
                'rows' => $rows->values()->map(fn (array $row, int $i) => [
                    $i + 1, $row['user']->name, $row['user']->username, ReportFilter::classLabel($row['user']), $status($row),
                    $row['record']?->checked_in_at ? jam($row['record']->checked_in_at) : null,
                    $row['record']?->reject_reason ?? $row['record']?->approval_note,
                ])->all(),
            ]],
        ];
    }

    protected function attendanceRecap(ReportFilter $filter): array
    {
        $data = $this->attendance->daily($filter);
        $statuses = ['present', 'late', 'excused', 'sick', 'absent', 'rejected', 'missing'];
        $below = $this->belowTarget($data['participants'], $filter);

        return [
            'summary' => [
                ['label' => 'Peserta', 'value' => (string) $data['participants']->count()],
                ['label' => 'Kehadiran rata-rata', 'value' => $this->percent($data['rate'])],
                ['label' => 'Terlambat', 'value' => (string) $data['totals']['late']],
                ['label' => 'Izin / sakit', 'value' => (string) ($data['totals']['excused'] + $data['totals']['sick'])],
                ['label' => 'Tidak absen', 'value' => (string) ($data['totals']['missing'] + $data['totals']['absent'])],
                ['label' => 'Di bawah target', 'value' => (string) $below],
            ],
            'sheets' => [
                [
                    'title' => 'Rekap per peserta',
                    'headings' => ['No', 'Nama', 'NIS', 'Kelas', 'Hari absen', ...array_map(fn ($s) => AttendanceAnalytics::label($s), $statuses), 'Kehadiran (%)'],
                    'rows' => $data['participants']->values()->map(fn (array $row, int $i) => [
                        $i + 1, $row['user']->name, $row['user']->username, ReportFilter::classLabel($row['user']), $row['expected'],
                        ...array_map(fn ($s) => $row['counts'][$s], $statuses), $row['rate'],
                    ])->all(),
                ],
                [
                    'title' => 'Rekap per hari',
                    'headings' => ['Tanggal', 'Wajib absen', ...array_map(fn ($s) => AttendanceAnalytics::label($s), $statuses), 'Kehadiran (%)'],
                    'rows' => $data['days']->filter(fn ($day) => $day['expected'] > 0)->values()->map(fn (array $day) => [
                        tanggal($day['date'], 'D, j M Y'), $day['expected'], ...array_map(fn ($s) => $day['counts'][$s], $statuses), $day['rate'],
                    ])->all(),
                ],
            ],
        ];
    }

    protected function sessionAttendance(ReportFilter $filter): array
    {
        $sessions = $this->attendance->sessions($filter);
        $names = $filter->participants()->keyBy('id');
        $expected = (int) $sessions->sum('expected');
        $attended = (int) $sessions->sum('attended');
        $statuses = ['present', 'late', 'excused', 'sick', 'absent', 'missing'];

        $detail = [];
        foreach ($sessions as $row) {
            $session = $row['session'];
            foreach ($names->filter(fn (User $u) => $u->classrooms->contains('id', $session->classroom_id)) as $user) {
                $attendance = $row['attendances']->get($user->id);
                $detail[] = [
                    tanggal($session->date, 'D, j M Y'), $session->classroom->name, $session->topic, $user->name,
                    $attendance ? $attendance->status->label() : 'Tidak tercatat',
                    $attendance?->submitted_at ? jam($attendance->submitted_at) : null,
                ];
            }
        }

        return [
            'summary' => [
                ['label' => 'Sesi', 'value' => (string) $sessions->count()],
                ['label' => 'Kehadiran rata-rata', 'value' => $this->percent($expected ? $attended / $expected * 100 : null)],
                ['label' => 'Alpa', 'value' => (string) $sessions->sum(fn ($r) => $r['counts']['absent'])],
                ['label' => 'Tidak tercatat', 'value' => (string) $sessions->sum(fn ($r) => $r['counts']['missing'])],
            ],
            'sheets' => [
                [
                    'title' => 'Per sesi',
                    'headings' => ['Tanggal', 'Jam', 'Kelas', 'Topik', 'Pengajar', 'Peserta', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Tidak tercatat', 'Kehadiran (%)'],
                    'rows' => $sessions->map(fn (array $row) => [
                        tanggal($row['session']->date, 'D, j M Y'), $row['session']->timeRange(), $row['session']->classroom->name,
                        $row['session']->topic, $row['session']->teacher?->name, $row['expected'],
                        ...array_map(fn ($s) => $row['counts'][$s], $statuses), $row['rate'],
                    ])->values()->all(),
                ],
                [
                    'title' => 'Rincian peserta',
                    'headings' => ['Tanggal', 'Kelas', 'Topik', 'Nama', 'Status', 'Waktu input'],
                    'rows' => $detail,
                ],
            ],
        ];
    }

    protected function mealReceipts(ReportFilter $filter): array
    {
        $data = $this->meals->compute($filter);
        $names = $filter->participants()->keyBy('id');
        $types = MealType::cases();

        $detail = [];
        foreach ($data['schedules'] as $row) {
            foreach ($row['schedule']->receipts as $receipt) {
                $detail[] = [
                    tanggal($row['schedule']->date, 'D, j M Y'), $row['schedule']->meal_type->label(), $row['schedule']->vendor?->name,
                    $names->get($receipt->user_id)?->name, jam($receipt->received_at), $receipt->proxy?->name, $receipt->note,
                ];
            }
        }

        return [
            'summary' => [
                ['label' => 'Jadwal', 'value' => (string) $data['schedules']->count()],
                ['label' => 'Tanda terima', 'value' => angka($data['totals']['received'])],
                ['label' => 'Diwakilkan', 'value' => angka($data['totals']['proxied'])],
                ['label' => 'Serapan', 'value' => $this->percent($data['totals']['rate'])],
            ],
            'sheets' => [
                [
                    'title' => 'Per jadwal',
                    'headings' => ['Tanggal', 'Waktu makan', 'Vendor', 'Dipesan', 'Diterima dari vendor', 'Peserta berhak', 'Tanda terima', 'Diwakilkan', 'Serapan (%)'],
                    'rows' => $data['schedules']->map(fn (array $row) => [
                        tanggal($row['schedule']->date, 'D, j M Y'), $row['schedule']->meal_type->label(), $row['schedule']->vendor?->name,
                        $row['schedule']->portions_ordered, $row['schedule']->delivery?->quantityLabel(), $row['eligible'], $row['received'], $row['proxied'], $row['rate'],
                    ])->values()->all(),
                ],
                [
                    'title' => 'Per peserta',
                    'headings' => ['No', 'Nama', 'Kelas', ...array_map(fn (MealType $t) => $t->label(), $types), 'Total'],
                    'rows' => $filter->participants()->values()->map(function (User $user, int $i) use ($data, $types) {
                        $counts = $data['participants'][$user->id] ?? [];

                        return [$i + 1, $user->name, ReportFilter::classLabel($user), ...array_map(fn (MealType $t) => $counts[$t->value] ?? 0, $types), array_sum($counts)];
                    })->all(),
                ],
                [
                    'title' => 'Per vendor',
                    'headings' => ['Vendor', 'Jadwal', 'Dipesan', 'Diterima (pcs)', 'Diterima (dos)', 'Tanda terima'],
                    'rows' => $data['vendors']->map(fn (array $row) => [
                        $row['vendor']?->name, $row['schedules'], $row['ordered'], $row['delivered']['pcs'] ?? 0, $row['delivered']['box'] ?? 0, $row['received'],
                    ])->all(),
                ],
                [
                    'title' => 'Rincian tanda terima',
                    'headings' => ['Tanggal', 'Waktu makan', 'Vendor', 'Nama', 'Jam terima', 'Diwakilkan oleh', 'Catatan'],
                    'rows' => $detail,
                ],
            ],
        ];
    }

    /** Input tim konsumsi berlaku untuk semua peserta (tidak difilter kelas). */
    protected function mealDeliveries(ReportFilter $filter): array
    {
        $schedules = MealSchedule::query()->with(['vendor', 'delivery.reporter'])->withCount('receipts')
            ->whereDateBetween('date', $filter->from->toDateString(), $filter->to->toDateString())
            ->orderBy('date')->orderBy('starts_at')->get();
        $inputs = $schedules->filter(fn (MealSchedule $s) => $s->delivery !== null)->values();

        $vendors = $inputs->groupBy('vendor_id')->map(fn (Collection $rows) => [
            $rows->first()->vendor?->name, $rows->count(),
            (int) $rows->filter(fn ($s) => $s->delivery->unit->value === 'pcs')->sum(fn ($s) => $s->delivery->quantity),
            (int) $rows->filter(fn ($s) => $s->delivery->unit->value === 'box')->sum(fn ($s) => $s->delivery->quantity),
            (int) $rows->sum('receipts_count'),
        ])->sortBy(fn ($row) => $row[0])->values()->all();

        return [
            'summary' => [
                ['label' => 'Input', 'value' => (string) $inputs->count()],
                ['label' => 'Jadwal belum diinput', 'value' => (string) ($schedules->count() - $inputs->count())],
                ['label' => 'Vendor', 'value' => (string) count($vendors)],
                ['label' => 'Tanda terima', 'value' => angka($inputs->sum('receipts_count'))],
            ],
            'sheets' => [
                [
                    'title' => 'Input konsumsi',
                    'headings' => ['Tanggal', 'Waktu makan', 'Vendor', 'Jumlah', 'Satuan', 'Menu', 'Jam siap', 'Tempat', 'Diinput oleh', 'Waktu input', 'Tanda terima', 'Selisih'],
                    'rows' => $inputs->map(fn (MealSchedule $s) => [
                        tanggal($s->date, 'D, j M Y'), $s->meal_type->label(), $s->vendor?->name, $s->delivery->quantity, $s->delivery->unit->label(),
                        str_replace("\n", ', ', trim($s->delivery->menu)), jam($s->delivery->ready_at), $s->delivery->location,
                        $s->delivery->reporter?->name, tanggal_jam($s->delivery->updated_at), $s->receipts_count, $s->delivery->quantity - $s->receipts_count,
                    ])->all(),
                ],
                [
                    'title' => 'Per vendor',
                    'headings' => ['Vendor', 'Jumlah input', 'Total pcs', 'Total dos', 'Tanda terima'],
                    'rows' => $vendors,
                ],
            ],
        ];
    }

    protected function scoreRecap(ReportFilter $filter, ReportPeriod $period): array
    {
        $rows = $this->scores->perParticipant($filter);
        $passing = $this->passingScore($filter);
        $scored = $rows->whereNotNull('overall');

        $sheets = [[
            'title' => 'Rekap nilai',
            'headings' => ['No', 'Nama', 'NIS', 'Kelas', 'Sesi', 'Tugas', 'Quiz', 'Penilaian periodik', 'Rata-rata', 'Predikat'],
            'rows' => $rows->values()->map(fn (array $row, int $i) => [
                $i + 1, $row['user']->name, $row['user']->username, ReportFilter::classLabel($row['user']),
                $row['session'], $row['assignment'], $row['quiz'], $row['periodic'], $row['overall'], $row['grade'],
            ])->all(),
        ]];

        if ($period === ReportPeriod::Monthly) {
            $weeks = $filter->weeks();
            $weekly = collect($weeks)->map(fn (CarbonImmutable $week) => $this->scores->perParticipant($filter->between($week->max($filter->from), $week->endOfWeek()->min($filter->to)))->keyBy(fn ($row) => $row['user']->id));
            $sheets[] = [
                'title' => 'Per minggu',
                'headings' => ['No', 'Nama', 'Kelas', ...array_map(fn (CarbonImmutable $w) => 'Minggu '.tanggal($w->max($filter->from), 'j M'), $weeks), 'Rata-rata bulan'],
                'rows' => $rows->values()->map(fn (array $row, int $i) => [
                    $i + 1, $row['user']->name, ReportFilter::classLabel($row['user']),
                    ...$weekly->map(fn (Collection $week) => $week->get($row['user']->id)['overall'] ?? null)->all(), $row['overall'],
                ])->all(),
            ];
        }

        return [
            'summary' => [
                ['label' => 'Peserta dinilai', 'value' => $scored->count().' / '.$rows->count()],
                ['label' => 'Rata-rata', 'value' => $this->number(GradeService::average($scored->pluck('overall')))],
                ['label' => 'Tertinggi', 'value' => $this->number($scored->max('overall'))],
                ['label' => 'Di bawah '.angka($passing), 'value' => (string) $scored->filter(fn ($row) => $row['overall'] < $passing)->count()],
            ],
            'sheets' => $sheets,
        ];
    }

    /** @param Collection<int, array{rate: ?float, user: User}> $participants */
    protected function belowTarget(Collection $participants, ReportFilter $filter): int
    {
        $target = (float) (Batch::query()->find($filter->batchId)?->attendance_target ?? 80);

        return $participants->filter(fn ($row) => $row['rate'] !== null && $row['rate'] < $target)->count();
    }

    protected function passingScore(ReportFilter $filter): float
    {
        return (float) (Batch::query()->find($filter->batchId)?->passing_score ?? 70);
    }

    protected function percent(?float $value): string
    {
        return $value === null ? '–' : angka($value, 1).'%';
    }

    protected function number(?float $value): string
    {
        return $value === null ? '–' : angka($value, 1);
    }
}
