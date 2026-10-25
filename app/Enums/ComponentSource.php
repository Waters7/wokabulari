<?php

namespace App\Enums;

enum ComponentSource: string
{
    case Manual = 'manual';
    case Quiz = 'quiz';
    case Assignment = 'assignment';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Quiz => 'Quiz',
            self::Assignment => 'Tugas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'zinc',
            self::Quiz => 'purple',
            self::Assignment => 'blue',
        };
    }
}
