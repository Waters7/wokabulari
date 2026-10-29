<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Leader = 'leader';
    case ProgramAdmin = 'program_admin';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Participant = 'participant';
    case Guardian = 'guardian';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Leader => 'Pimpinan',
            self::ProgramAdmin => 'Admin Program',
            self::Teacher => 'Pengajar',
            self::Staff => 'Tim Kerja',
            self::Participant => 'Peserta',
            self::Guardian => 'Orang Tua/Wali',
            self::Vendor => 'Vendor Makanan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'red',
            self::Leader => 'purple',
            self::ProgramAdmin => 'blue',
            self::Teacher => 'green',
            self::Staff => 'amber',
            self::Participant => 'emerald',
            self::Guardian => 'zinc',
            self::Vendor => 'orange',
        };
    }

    /** Role yang memakai bottom navigation di HP. */
    public function usesBottomNav(): bool
    {
        return in_array($this, [self::Participant, self::Guardian, self::Staff, self::Vendor], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
