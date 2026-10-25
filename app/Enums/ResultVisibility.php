<?php

namespace App\Enums;

enum ResultVisibility: string
{
    case Immediately = 'immediately';
    case AfterClose = 'after_close';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Immediately => 'Langsung setelah dikumpulkan',
            self::AfterClose => 'Setelah quiz ditutup',
            self::Manual => 'Dirilis manual oleh pengajar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Immediately => 'green',
            self::AfterClose => 'amber',
            self::Manual => 'zinc',
        };
    }
}
