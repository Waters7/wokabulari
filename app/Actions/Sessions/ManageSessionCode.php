<?php

namespace App\Actions\Sessions;

use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Models\LearningSession;
use App\Models\SessionAttendanceCode;
use App\Models\User;
use App\Services\SessionDocumentationRule;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mulai sesi dan kelola kode kehadiran: buat, perpanjang, ganti (kode lama nonaktif), tutup.
 */
class ManageSessionCode
{
    public function __construct(protected SessionDocumentationRule $documentation) {}

    public function start(LearningSession $session, User $by): SessionAttendanceCode
    {
        if (in_array($session->status, [SessionStatus::Completed, SessionStatus::Cancelled], true)) {
            throw new InvalidArgumentException('Sesi sudah '.strtolower($session->status->label()).'.');
        }

        if ($session->status === SessionStatus::Scheduled) {
            $this->documentation->ensure($session, SessionPhase::Start);
        }

        $session->update([
            'status' => SessionStatus::Ongoing,
            'started_at' => $session->started_at ?? now(),
        ]);

        return $session->activeCode ?? $this->generate($session, $by);
    }

    /** Buat kode baru; semua kode lama sesi ini dinonaktifkan. */
    public function generate(LearningSession $session, User $by): SessionAttendanceCode
    {
        if (! $session->isOngoing()) {
            throw new InvalidArgumentException('Mulai sesi terlebih dahulu.');
        }

        return DB::transaction(function () use ($session, $by) {
            $session->codes()->where('is_active', true)->update(['is_active' => false]);

            do {
                $code = SessionAttendanceCode::randomCode();
            } while (SessionAttendanceCode::query()->where('code', $code)->where('is_active', true)->exists());

            $created = $session->codes()->create([
                'code' => $code,
                'expires_at' => now()->addMinutes((int) setting('session.code_duration_minutes')),
                'is_active' => true,
                'generated_by' => $by->id,
            ]);

            $session->unsetRelation('activeCode');

            return $created;
        });
    }

    public function extend(LearningSession $session, int $minutes = 5): SessionAttendanceCode
    {
        $code = $session->codes()->where('is_active', true)->latest('id')->first();

        if (! $code) {
            throw new InvalidArgumentException('Tidak ada kode aktif.');
        }

        $base = $code->expires_at->isFuture() ? $code->expires_at : now();
        $code->update(['expires_at' => $base->copy()->addMinutes(max(1, min(60, $minutes)))]);

        return $code;
    }

    public function close(LearningSession $session): void
    {
        $session->codes()->where('is_active', true)->update(['is_active' => false]);
        $session->unsetRelation('activeCode');
    }

    /** Dipakai scheduler: nonaktifkan semua kode yang sudah kedaluwarsa. */
    public static function closeExpired(): int
    {
        return SessionAttendanceCode::query()
            ->where('is_active', true)
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);
    }
}
