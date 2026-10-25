<?php

namespace App\Enums;

/**
 * Status absen asrama malam (SPEC 3.15).
 */
enum DormAttendanceStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case NotPresent = 'not_present';
    case OnLeave = 'on_leave';
    case SickInRoom = 'sick_in_room';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'Menunggu konfirmasi',
            self::Confirmed => 'Terkonfirmasi',
            self::NotPresent => 'Tidak di asrama',
            self::OnLeave => 'Izin keluar',
            self::SickInRoom => 'Sakit di kamar',
            self::Rejected => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'amber',
            self::Confirmed => 'green',
            self::NotPresent => 'red',
            self::OnLeave => 'sky',
            self::SickInRoom => 'violet',
            self::Rejected => 'rose',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /** Peserta dianggap berada di asrama malam itu. */
    public function isInDorm(): bool
    {
        return in_array($this, [self::PendingConfirmation, self::Confirmed, self::SickInRoom], true);
    }
}
