<?php

namespace App\Enums;

enum SpecialMealStatus: string
{
    case Pending = 'pending';
    case Prepared = 'prepared';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Prepared => 'Disiapkan',
            self::Delivered => 'Sudah diantar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Prepared => 'blue',
            self::Delivered => 'green',
        };
    }
}
