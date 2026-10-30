<?php

namespace App\Enums;

enum LanguageAspect: string
{
    case Listening = 'listening';
    case Speaking = 'speaking';
    case Reading = 'reading';
    case Writing = 'writing';
    case Vocabulary = 'vocabulary';
    case Grammar = 'grammar';
    case Attitude = 'attitude';
    case Participation = 'participation';

    public function label(): string
    {
        return match ($this) {
            self::Listening => 'Menyimak',
            self::Speaking => 'Berbicara',
            self::Reading => 'Membaca',
            self::Writing => 'Menulis',
            self::Vocabulary => 'Kosakata',
            self::Grammar => 'Tata bahasa',
            self::Attitude => 'Sikap',
            self::Participation => 'Partisipasi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Listening => 'sky',
            self::Speaking => 'rose',
            self::Reading => 'indigo',
            self::Writing => 'violet',
            self::Vocabulary => 'emerald',
            self::Grammar => 'amber',
            self::Attitude => 'teal',
            self::Participation => 'lime',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $a) => [$a->value => $a->label()])->all();
    }
}
