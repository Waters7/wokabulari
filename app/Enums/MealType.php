<?php

namespace App\Enums;

/**
 * Jenis konsumsi, urut sesuai waktu dalam sehari.
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case MorningSnack = 'morning_snack';
    case Lunch = 'lunch';
    case AfternoonSnack = 'afternoon_snack';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Sarapan',
            self::MorningSnack => 'Snack pagi',
            self::Lunch => 'Makan siang',
            self::AfternoonSnack => 'Snack sore',
            self::Dinner => 'Makan malam',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Breakfast => 'amber',
            self::MorningSnack, self::AfternoonSnack => 'lime',
            self::Lunch => 'green',
            self::Dinner => 'indigo',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Breakfast => 'sun',
            self::MorningSnack, self::AfternoonSnack => 'gift',
            self::Lunch => 'cake',
            self::Dinner => 'moon',
        };
    }

    public function isSnack(): bool
    {
        return in_array($this, [self::MorningSnack, self::AfternoonSnack], true);
    }

    /** Rentang pengambilan bawaan (jam mulai, jam selesai). @return array{0: string, 1: string} */
    public function defaultWindow(): array
    {
        return match ($this) {
            self::Breakfast => ['06:00', '07:30'],
            self::MorningSnack => ['09:45', '10:30'],
            self::Lunch => ['11:30', '13:30'],
            self::AfternoonSnack => ['15:15', '16:00'],
            self::Dinner => ['17:30', '19:30'],
        };
    }
}
