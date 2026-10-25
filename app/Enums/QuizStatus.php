<?php

namespace App\Enums;

enum QuizStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
            self::Closed => 'Ditutup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'green',
            self::Closed => 'red',
        };
    }
}
