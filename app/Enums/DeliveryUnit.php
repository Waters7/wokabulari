<?php

namespace App\Enums;

/**
 * Satuan jumlah makanan yang diantar vendor.
 */
enum DeliveryUnit: string
{
    case Pcs = 'pcs';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Pcs => 'pcs',
            self::Box => 'dos',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pcs => 'sky',
            self::Box => 'amber',
        };
    }
}
