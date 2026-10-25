<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case ShortAnswer = 'short_answer';
    case Matching = 'matching';
    case Essay = 'essay';
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Tone = 'tone';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Pilihan ganda',
            self::TrueFalse => 'Benar/Salah',
            self::ShortAnswer => 'Isian singkat',
            self::Matching => 'Menjodohkan',
            self::Essay => 'Esai',
            self::Listening => 'Menyimak (audio)',
            self::Speaking => 'Berbicara (rekam suara)',
            self::Tone => 'Nada Mandarin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MultipleChoice => 'blue',
            self::TrueFalse => 'sky',
            self::ShortAnswer => 'teal',
            self::Matching => 'indigo',
            self::Essay => 'amber',
            self::Listening => 'purple',
            self::Speaking => 'rose',
            self::Tone => 'red',
        };
    }

    /** Dinilai otomatis oleh sistem. */
    public function isAutoGraded(): bool
    {
        return ! in_array($this, [self::Essay, self::Speaking], true);
    }

    /** Jawaban berupa satu opsi yang dipilih. */
    public function usesChoice(): bool
    {
        return in_array($this, [self::MultipleChoice, self::TrueFalse, self::Listening, self::Tone], true);
    }

    public function needsMedia(): bool
    {
        return $this === self::Listening;
    }

    public static function fromImport(string $value): ?self
    {
        $value = strtolower(trim($value));

        return self::tryFrom($value) ?? match ($value) {
            'pg', 'pilihan ganda', 'mc' => self::MultipleChoice,
            'bs', 'benar salah', 'benar/salah', 'tf' => self::TrueFalse,
            'isian', 'isian singkat' => self::ShortAnswer,
            'jodoh', 'menjodohkan' => self::Matching,
            'esai', 'essay' => self::Essay,
            'menyimak', 'audio' => self::Listening,
            'berbicara', 'rekam' => self::Speaking,
            'nada' => self::Tone,
            default => null,
        };
    }
}
