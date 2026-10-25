<?php

namespace App\Enums;

/**
 * Jenis tindakan penanganan laporan sakit.
 */
enum HealthActionType: string
{
    case Examination = 'examination';
    case Medication = 'medication';
    case Rest = 'rest';
    case Referral = 'referral';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Examination => 'Pemeriksaan',
            self::Medication => 'Pemberian obat',
            self::Rest => 'Istirahat',
            self::Referral => 'Rujukan',
            self::Monitoring => 'Pemantauan',
            self::Resolved => 'Selesai/pulih',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Examination => 'sky',
            self::Medication => 'indigo',
            self::Rest => 'zinc',
            self::Referral => 'orange',
            self::Monitoring => 'amber',
            self::Resolved => 'green',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }

    /** Status laporan setelah tindakan ini dicatat. */
    public function resultingStatus(): HealthReportStatus
    {
        return match ($this) {
            self::Monitoring => HealthReportStatus::Monitoring,
            self::Referral => HealthReportStatus::Referred,
            self::Resolved => HealthReportStatus::Recovered,
            default => HealthReportStatus::Handled,
        };
    }
}
