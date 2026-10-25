<?php

namespace App\Enums;

enum SubmissionType: string
{
    case Text = 'text';
    case File = 'file';
    case Photo = 'photo';
    case Audio = 'audio';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::File => 'Berkas',
            self::Photo => 'Foto',
            self::Audio => 'Rekaman suara',
            self::Mixed => 'Campuran',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Text => 'zinc',
            self::File => 'blue',
            self::Photo => 'amber',
            self::Audio => 'rose',
            self::Mixed => 'purple',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Text => 'pencil',
            self::File => 'paper-clip',
            self::Photo => 'camera',
            self::Audio => 'microphone',
            self::Mixed => 'squares-plus',
        };
    }

    public function allowsText(): bool
    {
        return in_array($this, [self::Text, self::Mixed], true);
    }

    public function allowsFiles(): bool
    {
        return in_array($this, [self::File, self::Mixed], true);
    }

    public function allowsPhotos(): bool
    {
        return in_array($this, [self::Photo, self::Mixed], true);
    }

    public function allowsAudio(): bool
    {
        return in_array($this, [self::Audio, self::Mixed], true);
    }
}
