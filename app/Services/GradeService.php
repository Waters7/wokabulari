<?php

namespace App\Services;

/**
 * Predikat nilai: A ≥ 85, B ≥ 75, C ≥ 65, D < 65 (batas dapat diatur di pengaturan sistem).
 */
class GradeService
{
    public function grade(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= (float) setting('grade.threshold_a') => 'A',
            $score >= (float) setting('grade.threshold_b') => 'B',
            $score >= (float) setting('grade.threshold_c') => 'C',
            default => 'D',
        };
    }

    public static function color(?string $grade): string
    {
        return match ($grade) {
            'A' => 'green',
            'B' => 'lime',
            'C' => 'amber',
            'D' => 'red',
            default => 'zinc',
        };
    }

    /**
     * Rata-rata nilai yang terisi (abaikan null), dibulatkan 2 desimal.
     *
     * @param  iterable<float|int|null>  $values
     */
    public static function average(iterable $values): ?float
    {
        $filled = collect($values)->filter(fn ($v) => $v !== null && $v !== '');

        return $filled->isEmpty() ? null : round((float) $filled->avg(), 2);
    }
}
