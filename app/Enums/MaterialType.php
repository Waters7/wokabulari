<?php

namespace App\Enums;

enum MaterialType: string
{
    case File = 'file';
    case Audio = 'audio';
    case Video = 'video';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Berkas',
            self::Audio => 'Audio',
            self::Video => 'Video',
            self::Link => 'Tautan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::File => 'blue',
            self::Audio => 'purple',
            self::Video => 'rose',
            self::Link => 'teal',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::File => 'document-text',
            self::Audio => 'musical-note',
            self::Video => 'film',
            self::Link => 'link',
        };
    }

    public static function fromMime(?string $mime): self
    {
        return match (true) {
            str_starts_with((string) $mime, 'audio/') => self::Audio,
            str_starts_with((string) $mime, 'video/') => self::Video,
            default => self::File,
        };
    }
}
