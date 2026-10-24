<?php

namespace App\Actions\Leave;

use App\Enums\ReviewStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestReviewed;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Setujui/tolak izin atau sakit kelas. Persetujuan mengisi status kehadiran di rentang tanggal.
 */
class ReviewLeaveRequest
{
    public function __construct(protected SyncLeaveAttendance $sync) {}

    public function handle(LeaveRequest $leave, User $reviewer, bool $approve, ?string $note = null): LeaveRequest
    {
        if ($leave->status !== ReviewStatus::Pending) {
            throw new InvalidArgumentException('Pengajuan ini sudah ditinjau.');
        }

        if (! $approve && blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        DB::transaction(function () use ($leave, $reviewer, $approve, $note) {
            $leave->update([
                'status' => $approve ? ReviewStatus::Approved : ReviewStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            if ($approve) {
                $this->sync->handle($leave);
            }
        });

        $leave->user->notify(new LeaveRequestReviewed($leave));

        return $leave;
    }
}
