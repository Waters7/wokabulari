<?php

namespace App\Exports;

use App\Enums\ReportType;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Semua jenis laporan Pusat Laporan dalam satu berkas Excel: lembar Ringkasan lalu lembar tiap laporan
 * (nama lembar diawali jenisnya, mis. "Harian - Rekap per peserta").
 */
class AllReportsExport implements Export, WithMultipleSheets
{
    /**
     * @param  list<array{type: ReportType, report: array<string, mixed>, scope: list<string>}>  $parts
     * @param  list<string>  $scope
     */
    public function __construct(protected array $parts, protected string $period, protected array $scope = []) {}

    public function sheets(): array
    {
        $summary = [];
        foreach ($this->parts as $part) {
            foreach ($part['report']['summary'] as $item) {
                $summary[] = [$part['type']->label(), $item['label'], $item['value']];
            }
        }

        $sheets = [new ReportSheet(
            ['title' => 'Ringkasan', 'headings' => ['Laporan', 'Ukuran', 'Nilai'], 'rows' => $summary],
            ['title' => 'Semua laporan', 'period' => $this->period],
            $this->scope,
        )];

        foreach ($this->parts as $part) {
            foreach ($part['report']['sheets'] as $sheet) {
                $sheet['title'] = $part['type']->shortLabel().' - '.$sheet['title'];
                $sheets[] = new ReportSheet($sheet, $part['report'], $part['scope']);
            }
        }

        return $sheets;
    }
}
