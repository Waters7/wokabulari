<?php

namespace App\Services;

use App\Enums\SessionPhase;
use App\Models\LearningSession;
use InvalidArgumentException;

/**
 * Aturan dokumentasi kelas: sesi pada/sesudah tanggal berlaku wajib punya foto/file saat mulai & akhir.
 */
class SessionDocumentationRule
{
    public function required(LearningSession $session): bool
    {
        $from = setting('session.documentation_from');

        return (bool) setting('session.documentation_required')
            && ($from === null || $from === '' || $session->date->toDateString() >= (string) $from);
    }

    public function missing(LearningSession $session, SessionPhase $phase): bool
    {
        return $this->required($session) && ! $session->documentations()->where('phase', $phase)->exists();
    }

    /** Tolak mulai/akhiri sesi bila dokumentasi wajib belum ada. */
    public function ensure(LearningSession $session, SessionPhase $phase): void
    {
        if ($this->missing($session, $phase)) {
            throw new InvalidArgumentException($phase === SessionPhase::Start
                ? 'Ambil foto kelas atau unggah file dokumentasi dulu sebelum memulai sesi.'
                : 'Ambil foto kelas atau unggah file dokumentasi akhir sesi dulu sebelum menyelesaikan sesi.');
        }
    }
}
