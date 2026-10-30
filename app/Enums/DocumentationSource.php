<?php

namespace App\Enums;

enum DocumentationSource: string
{
    case Camera = 'camera';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Camera => 'Foto kamera',
            self::Upload => 'File unggahan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Camera => 'sky',
            self::Upload => 'zinc',
        };
    }
}
