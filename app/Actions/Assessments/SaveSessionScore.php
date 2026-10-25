<?php

namespace App\Actions\Assessments;

use App\Models\SessionAssessmentComponent;
use App\Models\SessionScore;
use App\Models\User;
use InvalidArgumentException;

/**
 * Simpan skor satu sel (peserta × komponen). Nilai kosong menghapus skor.
 */
class SaveSessionScore
{
    public function handle(SessionAssessmentComponent $component, User $participant, mixed $score): ?SessionScore
    {
        if (! $component->isEditable()) {
            throw new InvalidArgumentException('Komponen ini diisi otomatis.');
        }

        if (! $component->session->classroom->participants()->whereKey($participant->id)->exists()) {
            throw new InvalidArgumentException('Peserta bukan anggota kelas ini.');
        }

        if ($score === null || $score === '') {
            SessionScore::query()->where('session_assessment_component_id', $component->id)->where('user_id', $participant->id)->delete();

            return null;
        }

        $value = str_replace(',', '.', (string) $score);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Nilai harus berupa angka.');
        }

        $value = round((float) $value, 2);

        if ($value < 0 || $value > $component->max_score) {
            throw new InvalidArgumentException('Nilai harus 0–'.angka($component->max_score).'.');
        }

        return SessionScore::query()->updateOrCreate(
            ['session_assessment_component_id' => $component->id, 'user_id' => $participant->id],
            ['score' => $value],
        );
    }
}
