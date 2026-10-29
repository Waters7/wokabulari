<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan Pusat Laporan sebagai Excel: satu lembar per tabel, diawali judul, periode, dan waktu cetak.
 */
class ReportExport implements Export, WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array{title: string, period: string, summary: list<array{label: string, value: string}>, sheets: list<array{title: string, headings: list<string>, rows: list<list<mixed>>}>}  $report
     * @param  list<string>  $scope  keterangan filter, mis. ["Angkatan 1 2026", "Kelas Inggris A"]
     */
    public function __construct(protected array $report, protected array $scope = []) {}

    public function sheets(): array
    {
        return array_map(fn (array $sheet) => new ReportSheet($sheet, $this->report, $this->scope), $this->report['sheets']);
    }
}
