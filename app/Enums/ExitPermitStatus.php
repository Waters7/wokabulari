<?php

namespace App\Enums;

/**
 * Status izin keluar asrama (SPEC 3.16).
 */
enum ExitPermitStatus: string
{
    case Submitted = 'submitted';
    case GuardianApproved = 'guardian_approved';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Out = 'out';
    case Returned = 'returned';
    case LateReturn = 'late_return';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::GuardianApproved => 'Disetujui wali',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Out => 'Sedang keluar',
            self::Returned => 'Sudah kembali',
            self::LateReturn => 'Terlambat kembali',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'amber',
            self::GuardianApproved => 'sky',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Out => 'blue',
            self::Returned => 'zinc',
            self::LateReturn => 'rose',
            self::Cancelled => 'zinc',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /** Masih menunggu keputusan (wali atau pengelola). */
    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::GuardianApproved], true);
    }

    /** Izin berlaku: boleh dipindai keluar/masuk. */
    public function isActive(): bool
    {
        return in_array($this, [self::Approved, self::Out, self::LateReturn], true);
    }

    /** Peserta sedang berada di luar asrama. */
    public function isOutside(): bool
    {
        return in_array($this, [self::Out, self::LateReturn], true);
    }

    /** Dihitung untuk batas pengajuan per minggu. */
    public function countsTowardLimit(): bool
    {
        return ! in_array($this, [self::Rejected, self::Cancelled], true);
    }
}
