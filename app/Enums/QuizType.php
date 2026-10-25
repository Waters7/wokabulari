<?php

namespace App\Enums;

enum QuizType: string
{
    case Quiz = 'quiz';
    case Pretest = 'pretest';
    case Posttest = 'posttest';
    case Tryout = 'tryout';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::Pretest => 'Pretest',
            self::Posttest => 'Posttest',
            self::Tryout => 'Tryout',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Quiz => 'blue',
            self::Pretest => 'amber',
            self::Posttest => 'green',
            self::Tryout => 'purple',
        };
    }
}
