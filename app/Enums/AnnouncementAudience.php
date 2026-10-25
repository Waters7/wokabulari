<?php

namespace App\Enums;

/**
 * Sasaran pengumuman (SPEC 3.22): semua, per kelas, per asrama, per role.
 */
enum AnnouncementAudience: string
{
    case All = 'all';
    case Classroom = 'classroom';
    case Dormitory = 'dormitory';
    case Role = 'role';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Semua',
            self::Classroom => 'Kelas',
            self::Dormitory => 'Asrama',
            self::Role => 'Peran',
        };
    }

    public function color(): string
    {
        return 'zinc';
    }

    /**
     * Sasaran yang tersedia per sumber: pengajar hanya kelasnya, asrama hanya asrama.
     *
     * @return list<self>
     */
    public static function forSource(AnnouncementSource $source): array
    {
        return match ($source) {
            AnnouncementSource::Teacher => [self::Classroom],
            AnnouncementSource::Dormitory => [self::Dormitory, self::All],
            AnnouncementSource::Team => self::cases(),
        };
    }
}
