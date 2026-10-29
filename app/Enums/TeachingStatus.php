<?php

namespace App\Enums;

/**
 * Kehadiran pengajar pada satu sesi: hadir bila membuat kode kehadiran dan ada peserta yang hadir.
 */
enum TeachingStatus: string
{
    case Present = 'present';
    case NoParticipants = 'no_participants';
    case NotRecorded = 'not_recorded';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::NoParticipants => 'Kode dibuat, belum ada peserta',
            self::NotRecorded => 'Tidak tercatat',
            self::Upcoming => 'Belum dimulai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::NoParticipants => 'amber',
            self::NotRecorded => 'red',
            self::Upcoming => 'zinc',
        };
    }
}
