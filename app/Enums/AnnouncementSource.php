<?php

namespace App\Enums;

use App\Models\User;

/**
 * Sumber pengumuman — dibedakan warna & ikon agar peserta tahu siapa yang mengumumkan.
 */
enum AnnouncementSource: string
{
    case Team = 'team';
    case Teacher = 'teacher';
    case Dormitory = 'dormitory';

    public function label(): string
    {
        return match ($this) {
            self::Team => 'Tim Kerja',
            self::Teacher => 'Pengajar',
            self::Dormitory => 'Asrama',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Team => 'teal',
            self::Teacher => 'lime',
            self::Dormitory => 'violet',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Team => 'megaphone',
            self::Teacher => 'academic-cap',
            self::Dormitory => 'home-modern',
        };
    }

    /** Kelas Tailwind untuk penanda sumber (garis & ikon kartu). */
    public function accentClasses(): string
    {
        return match ($this) {
            self::Team => 'border-s-woka bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light',
            self::Teacher => 'border-s-kunyit bg-kunyit-50 text-kunyit-700 dark:bg-hutan-800 dark:text-kunyit',
            self::Dormitory => 'border-s-violet-500 bg-violet-50 text-violet-700 dark:bg-hutan-800 dark:text-violet-300',
        };
    }

    /**
     * Sumber yang boleh dipakai pengguna: pengajar → Pengajar, unit asrama → Asrama,
     * admin program / tim kerja lain → Tim Kerja. Super admin boleh semua.
     *
     * @return list<self>
     */
    public static function allowedFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return self::cases();
        }

        $sources = [];
        if ($user->hasRole(Role::ProgramAdmin->value) || $user->hasUnit(StaffUnit::Consumption) || $user->hasUnit(StaffUnit::Health)) {
            $sources[] = self::Team;
        }
        if ($user->isTeacher()) {
            $sources[] = self::Teacher;
        }
        if ($user->hasUnit(StaffUnit::Dormitory)) {
            $sources[] = self::Dormitory;
        }

        return $sources;
    }
}
