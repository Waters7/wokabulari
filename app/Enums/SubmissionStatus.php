<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Late = 'late';
    case Graded = 'graded';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Belum dikumpulkan',
            self::Submitted => 'Dikumpulkan',
            self::Late => 'Terlambat',
            self::Graded => 'Dinilai',
            self::RevisionRequested => 'Perlu revisi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotSubmitted => 'zinc',
            self::Submitted => 'blue',
            self::Late => 'amber',
            self::Graded => 'green',
            self::RevisionRequested => 'red',
        };
    }

    /** Menunggu dinilai pengajar. */
    public function awaitingGrade(): bool
    {
        return in_array($this, [self::Submitted, self::Late], true);
    }
}
