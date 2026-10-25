<?php

namespace App\Enums;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Sarapan',
            self::Lunch => 'Makan siang',
            self::Dinner => 'Makan malam',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Breakfast => 'amber',
            self::Lunch => 'green',
            self::Dinner => 'indigo',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Breakfast => 'sun',
            self::Lunch => 'cake',
            self::Dinner => 'moon',
        };
    }

    /** Rentang pengambilan bawaan (jam mulai, jam selesai). @return array{0: string, 1: string} */
    public function defaultWindow(): array
    {
        return match ($this) {
            self::Breakfast => ['06:00', '07:30'],
            self::Lunch => ['11:30', '13:30'],
            self::Dinner => ['17:30', '19:30'],
        };
    }
}
