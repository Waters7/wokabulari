<?php

namespace App\Enums;

/**
 * Jenis survei/evaluasi yang dibuat tim kerja.
 */
enum EvaluationCategory: string
{
    case Facility = 'facility';
    case Program = 'program';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Facility => 'Sarana & prasarana',
            self::Program => 'Evaluasi program',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Facility => 'teal',
            self::Program => 'sky',
            self::Other => 'zinc',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Facility => 'building-office-2',
            self::Program => 'clipboard-document-list',
            self::Other => 'chat-bubble-left-ellipsis',
        };
    }
}
