<?php

namespace App\Actions\Sessions;

use App\Enums\AttendanceStatus;
use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Events\ParticipantMarkedAbsent;
use App\Models\LearningSession;
use App\Models\LeaveRequest;
use App\Models\SessionAttendance;
use App\Models\User;
use App\Services\SessionDocumentationRule;
use Illuminate\Support\Facades\DB;

/**
 * Selesaikan sesi: peserta yang belum input kode ditandai alpa, kecuali punya izin/sakit yang disetujui.
 * Sesi yang wajib dokumentasi harus punya foto/file akhir sesi.
 */
class EndSession
{
    public function __construct(protected SessionDocumentationRule $documentation) {}

    public function handle(LearningSession $session, User $by): int
    {
        $this->documentation->ensure($session, SessionPhase::End);

        $absent = [];

        $count = DB::transaction(function () use ($session, $by, &$absent) {
            $session->codes()->where('is_active', true)->update(['is_active' => false]);
            $session->update([
                'status' => SessionStatus::Completed,
                'started_at' => $session->started_at ?? now(),
                'ended_at' => now(),
            ]);

            $recorded = $session->attendances()->pluck('user_id');
            $missing = $session->classroom->participants()->whereNotIn('users.id', $recorded)->pluck('users.id');

            $leaves = LeaveRequest::query()->approved()->covering($session->date)->whereIn('user_id', $missing)->get()->keyBy('user_id');

            foreach ($missing as $userId) {
                $leave = $leaves->get($userId);

                SessionAttendance::query()->create([
                    'learning_session_id' => $session->id,
                    'user_id' => $userId,
                    'status' => $leave ? $leave->type->attendanceStatus() : AttendanceStatus::Absent,
                    'marked_by' => $by->id,
                    'note' => $leave ? 'Izin disetujui: '.$leave->reason : 'Otomatis: tidak input kode sampai sesi selesai',
                ]);

                if (! $leave) {
                    $absent[] = $userId;
                }
            }

            return $missing->count();
        });

        // Kabari wali setelah data tersimpan.
        foreach (User::query()->whereKey($absent)->get() as $participant) {
            event(new ParticipantMarkedAbsent($participant, $session));
        }

        return $count;
    }
}
