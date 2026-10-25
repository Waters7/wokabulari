<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case NeedsGrading = 'needs_grading';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang dikerjakan',
            self::Submitted => 'Dikumpulkan',
            self::NeedsGrading => 'Perlu dinilai',
            self::Graded => 'Dinilai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InProgress => 'blue',
            self::Submitted => 'sky',
            self::NeedsGrading => 'amber',
            self::Graded => 'green',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::InProgress;
    }
}
