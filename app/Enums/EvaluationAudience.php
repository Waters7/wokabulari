<?php

namespace App\Enums;

/**
 * Responden yang melihat sebuah pertanyaan survei.
 */
enum EvaluationAudience: string
{
    case All = 'all';
    case Participant = 'participant';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua responden',
            self::Participant => 'Peserta saja',
            self::Teacher => 'Pengajar saja',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::All => 'zinc',
            self::Participant => 'teal',
            self::Teacher => 'indigo',
        };
    }

    /** Apakah responden dengan peran ini melihat pertanyaan. */
    public function includes(string $role): bool
    {
        return $this === self::All || $this->value === $role;
    }
}
