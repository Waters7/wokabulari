<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\ExitPermitStatus;
use App\Enums\HealthReportStatus;
use App\Models\DailyAttendance;
use App\Models\DormExitPermit;
use App\Models\HealthReport;
use App\Models\LeaveRequest;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\User;
use App\Services\MealRoster;
use Illuminate\Support\Collection;

/**
 * Daftar tanda terima konsumsi per jadwal: semua penerima yang berhak, tanda tangan bila sudah menerima,
 * dan alasan bila tidak menerima (izin/sakit, keluar asrama, alpa, atau tanpa keterangan).
 * Alasan dicocokkan dengan tanggal & jam jadwal, bukan keadaan hari ini.
 */
class MealReceiptRoll
{
    public const NO_REASON = 'Tidak mengambil (tanpa keterangan)';

    public const NOT_FINISHED = 'Belum mengambil (jadwal belum selesai)';

    public function __construct(protected MealAnalytics $meals, protected MealRoster $roster) {}

    /**
     * @param  array<string, mixed>|null  $data  hasil MealAnalytics::compute() bila sudah dihitung
     * @return Collection<int, array{schedule: MealSchedule, rows: list<array{user: User, group: string, receipt: ?MealReceipt, reasons: list<string>}>, eligible: int, received: int, proxied: int, missed: int}>
     */
    public function build(ReportFilter $filter, ?array $data = null): Collection
    {
        $data ??= $this->meals->compute($filter);
        $staff = $data['staff'];
        $staffIds = $staff->pluck('id')->flip();
        $users = $filter->participants()->concat($staff)->values();
        $context = $this->context($filter, $filter->participants()->pluck('id'));

        return $data['schedules']->map(function (array $row) use ($users, $staffIds, $context) {
            $schedule = $row['schedule'];
            $receipts = $schedule->receipts->keyBy('user_id');
            $rows = [];

            foreach ($users as $user) {
                $receipt = $receipts->get($user->id);
                $isStaff = $staffIds->has($user->id);
                $eligible = $isStaff
                    ? $this->roster->servesStaff($schedule->meal_type)
                    : $user->profile?->lives_in_dorm !== false || $this->roster->servesNonResidents($schedule->meal_type);

                // Yang tidak berhak (mis. peserta tidak asrama untuk makan malam) hanya tampil bila tetap menerima.
                if (! $receipt && ! $eligible) {
                    continue;
                }

                $rows[] = [
                    'user' => $user,
                    'group' => $isStaff ? $user->role_label : ReportFilter::classLabel($user),
                    'receipt' => $receipt,
                    'reasons' => $receipt ? [] : $this->reasons($schedule, $user->id, $isStaff ? null : $context),
                ];
            }

            $missed = count(array_filter($rows, fn (array $r) => $r['receipt'] === null));

            return [
                'schedule' => $schedule,
                'rows' => $rows,
                'eligible' => $row['eligible'],
                'received' => $row['received'],
                'proxied' => $row['proxied'],
                'missed' => $missed,
            ];
        })->values();
    }

    /**
     * Data pendukung alasan untuk seluruh rentang laporan, dimuat sekali.
     *
     * @param  Collection<int, int>  $ids
     * @return array<string, Collection>
     */
    protected function context(ReportFilter $filter, Collection $ids): array
    {
        $from = $filter->from->startOfDay();
        $to = $filter->to->endOfDay();

        return [
            'leaves' => LeaveRequest::query()->approved()->whereIn('user_id', $ids)
                ->whereDate('date_from', '<=', $to)->whereDate('date_to', '>=', $from)->get()->groupBy('user_id'),
            'attendance' => DailyAttendance::query()->whereIn('user_id', $ids)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->whereIn('status', [AttendanceStatus::Excused, AttendanceStatus::Sick, AttendanceStatus::Absent])->get()
                ->keyBy(fn (DailyAttendance $a) => $a->user_id.'|'.$a->date->toDateString()),
            'permits' => DormExitPermit::query()->whereIn('user_id', $ids)
                ->whereIn('status', [ExitPermitStatus::Approved, ExitPermitStatus::Out, ExitPermitStatus::Returned, ExitPermitStatus::LateReturn])
                ->where(fn ($q) => $q->where('actual_departure', '<=', $to)->orWhere(fn ($q) => $q->whereNull('actual_departure')->where('planned_departure', '<=', $to)))
                ->get()->groupBy('user_id'),
            'health' => HealthReport::query()->whereIn('user_id', $ids)->where('reported_at', '<=', $to)
                ->where(fn ($q) => $q->where('resolved_at', '>=', $from)->orWhere(fn ($q) => $q->whereNull('resolved_at')->where('status', '!=', HealthReportStatus::Recovered)))
                ->get()->groupBy('user_id'),
        ];
    }

    /**
     * Alasan tidak menerima; tim (bukan peserta) tidak punya data izin/asrama sehingga context-nya null.
     *
     * @param  array<string, Collection>|null  $context
     * @return list<string>
     */
    protected function reasons(MealSchedule $schedule, int $userId, ?array $context): array
    {
        $reasons = [];
        $start = $schedule->starts_at;
        $end = $schedule->ends_at;
        $date = $schedule->date->toDateString();

        if ($end->isFuture()) {
            $reasons[] = self::NOT_FINISHED;
        }

        if ($context) {
            foreach ($context['leaves']->get($userId, collect()) as $leave) {
                if ($leave->date_from->toDateString() <= $date && $leave->date_to->toDateString() >= $date) {
                    $reasons[] = $leave->type->label();
                }
            }

            $attendance = $context['attendance']->get($userId.'|'.$date);
            if ($attendance) {
                $reasons[] = $attendance->status === AttendanceStatus::Absent ? 'Alpa di absen harian' : $attendance->status->label();
            }

            foreach ($context['permits']->get($userId, collect()) as $permit) {
                if ($this->outDuring($permit, $start, $end)) {
                    $reasons[] = 'Izin keluar asrama ('.$permit->type->label().')';
                }
            }

            foreach ($context['health']->get($userId, collect()) as $report) {
                $resolved = $report->resolved_at;
                if ($report->reported_at->lte($end) && ($resolved ? $resolved->gte($start) : $report->status !== HealthReportStatus::Recovered)) {
                    // Hanya "Sakit": rincian kesehatan tidak dicantumkan di laporan konsumsi.
                    $reasons[] = 'Sakit';
                }
            }
        }

        $reasons = array_values(array_unique($reasons));

        return $reasons === [] ? [self::NO_REASON] : $reasons;
    }

    /** Peserta berada di luar asrama selama jendela makan: pakai jam scan bila ada, selain itu rencana izin. */
    protected function outDuring(DormExitPermit $permit, $start, $end): bool
    {
        $left = $permit->actual_departure ?? ($permit->status === ExitPermitStatus::Approved ? $permit->planned_departure : null);
        if (! $left || $left->gt($end)) {
            return false;
        }

        $back = $permit->actual_return
            ?? (in_array($permit->status, [ExitPermitStatus::Out, ExitPermitStatus::LateReturn], true) ? null : $permit->planned_return);

        return $back === null || $back->gte($start);
    }
}
