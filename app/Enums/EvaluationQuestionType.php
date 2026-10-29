<?php

namespace App\Enums;

enum EvaluationQuestionType: string
{
    case Likert = 'likert';
    case Choice = 'choice';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Likert => 'Skala 1–5',
            self::Choice => 'Pilihan ganda',
            self::Text => 'Isian',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Likert => 'amber',
            self::Choice => 'sky',
            self::Text => 'zinc',
        };
    }

    /** Label skala Likert 1–5. @return array<int, string> */
    public static function likertLabels(): array
    {
        return [1 => 'Sangat buruk', 2 => 'Buruk', 3 => 'Cukup', 4 => 'Baik', 5 => 'Sangat baik'];
    }
}
