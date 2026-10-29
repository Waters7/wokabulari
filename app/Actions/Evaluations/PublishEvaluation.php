<?php

namespace App\Actions\Evaluations;

use App\Enums\PublishStatus;
use App\Models\ProgramEvaluation;
use App\Models\User;
use App\Notifications\EvaluationAssignedNotification;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Terbitkan, tutup, dan kirim pemberitahuan/pengingat survei.
 */
class PublishEvaluation
{
    /** @return int jumlah responden yang diberi tahu sekarang (0 bila survei terjadwal) */
    public function publish(ProgramEvaluation $evaluation, User $actor): int
    {
        if ($evaluation->questions()->doesntExist()) {
            throw new InvalidArgumentException('Tambahkan minimal satu pertanyaan sebelum menerbitkan.');
        }

        if ($evaluation->batches()->doesntExist()) {
            throw new InvalidArgumentException('Pilih minimal satu angkatan.');
        }

        if ($evaluation->isClosed()) {
            throw new InvalidArgumentException('Tenggat sudah lewat. Perpanjang tenggat dulu.');
        }

        $evaluation->update(['status' => PublishStatus::Published, 'published_at' => now()]);

        activity('survei')->causedBy($actor)->performedOn($evaluation)->log('Menerbitkan survei');

        return $evaluation->hasStarted() ? $this->notify($evaluation) : 0;
    }

    /** Tutup sebelum tenggat. */
    public function close(ProgramEvaluation $evaluation, User $actor): void
    {
        $evaluation->update(['closes_at' => now()]);

        activity('survei')->causedBy($actor)->performedOn($evaluation)->log('Menutup survei');
    }

    /** Beri tahu semua responden (sekali). */
    public function notify(ProgramEvaluation $evaluation): int
    {
        if ($evaluation->notified_at) {
            return 0;
        }

        $respondents = $evaluation->respondents();
        Notification::send($respondents, new EvaluationAssignedNotification($evaluation));
        $evaluation->forceFill(['notified_at' => now()])->save();

        return $respondents->count();
    }

    /** Ingatkan responden yang belum mengisi (sekali per tenggat). */
    public function remind(ProgramEvaluation $evaluation): int
    {
        if ($evaluation->reminded_at) {
            return 0;
        }

        $pending = $evaluation->respondents()->reject(fn (User $user) => $evaluation->hasResponded($user))->values();
        Notification::send($pending, new EvaluationAssignedNotification($evaluation, reminder: true));
        $evaluation->forceFill(['reminded_at' => now()])->save();

        return $pending->count();
    }
}
