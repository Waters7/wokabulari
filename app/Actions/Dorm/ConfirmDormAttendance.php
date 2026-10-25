<?php

namespace App\Actions\Dorm;

use App\Enums\DormAttendanceStatus;
use App\Models\DormAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Konfirmasi pengelola asrama: per orang, per kamar sekaligus, atau apel manual (SPEC 3.15).
 */
class ConfirmDormAttendance
{
    /**
     * Tandai status absen asrama seorang peserta pada tanggal tertentu (membuat catatan bila belum ada).
     */
    public function mark(User $by, User $participant, CarbonInterface|string $date, DormAttendanceStatus $status, ?string $note = null): DormAttendance
    {
        if ($status === DormAttendanceStatus::Rejected) {
            throw new InvalidArgumentException('Status tidak valid.');
        }

        if ($status === DormAttendanceStatus::NotPresent && blank($note) && ! $this->pastCurfew($participant, $date)) {
            throw new InvalidArgumentException('Catatan wajib diisi untuk menandai tidak di asrama sebelum jam malam.');
        }

        $date = CarbonImmutable::parse($date)->toDateString();
        $attendance = DormAttendance::query()->where('user_id', $participant->id)->whereDate('date', $date)->first()
            ?? new DormAttendance(['user_id' => $participant->id, 'date' => $date, 'attempts' => 0]);

        $attendance->fill([
            'dormitory_id' => $attendance->dormitory_id ?? $participant->currentDormitory()?->id,
            'status' => $status,
            'confirmed_by' => $by->id,
            'confirmed_at' => now(),
            'note' => filled($note) ? trim($note) : $attendance->note,
        ])->save();

        return $attendance;
    }

    /**
     * Konfirmasi semua absen mandiri yang menunggu (mis. satu kamar sekaligus).
     *
     * @param  list<int>  $attendanceIds
     */
    public function confirmMany(User $by, array $attendanceIds): int
    {
        return DB::transaction(fn () => DormAttendance::query()
            ->whereKey($attendanceIds)
            ->where('status', DormAttendanceStatus::PendingConfirmation)
            ->get()
            ->each(fn (DormAttendance $attendance) => $attendance->update([
                'status' => DormAttendanceStatus::Confirmed,
                'confirmed_by' => $by->id,
                'confirmed_at' => now(),
            ]))
            ->count());
    }

    protected function pastCurfew(User $participant, CarbonInterface|string $date): bool
    {
        $dormitory = $participant->currentDormitory();

        return $dormitory ? now()->gt($dormitory->curfewAt(CarbonImmutable::parse($date))) : true;
    }
}
