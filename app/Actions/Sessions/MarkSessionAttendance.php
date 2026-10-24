<?php

namespace App\Actions\Sessions;

use App\Enums\AttendanceStatus;
use App\Events\SessionAttendanceRecorded;
use App\Models\LearningSession;
use App\Models\SessionAttendance;
use App\Models\User;
use InvalidArgumentException;

/**
 * Pengajar menandai kehadiran sesi secara manual (izin/sakit/alpa/hadir) dengan alasan.
 */
class MarkSessionAttendance
{
    public function handle(LearningSession $session, User $participant, AttendanceStatus $status, string $note, User $by): SessionAttendance
    {
        if (! in_array($status, AttendanceStatus::manualOptions(), true)) {
            throw new InvalidArgumentException('Status tidak valid.');
        }

        if (trim($note) === '') {
            throw new InvalidArgumentException('Alasan wajib diisi.');
        }

        if (! $session->classroom->participants()->whereKey($participant->id)->exists()) {
            throw new InvalidArgumentException('Peserta bukan anggota kelas ini.');
        }

        $existing = SessionAttendance::query()->where('learning_session_id', $session->id)->where('user_id', $participant->id)->first();
        $wasAttended = $existing?->status?->countsAsAttended() ?? false;

        $attendance = SessionAttendance::query()->updateOrCreate(
            ['learning_session_id' => $session->id, 'user_id' => $participant->id],
            ['status' => $status, 'marked_by' => $by->id, 'note' => trim($note), 'submitted_at' => $existing?->submitted_at ?? ($status->countsAsAttended() ? now() : null)],
        );

        if (! $wasAttended && $status->countsAsAttended()) {
            event(new SessionAttendanceRecorded($attendance));
        }

        return $attendance;
    }
}
