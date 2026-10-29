<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ReportSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    /** Baris kepala sebelum judul kolom (judul, periode, cakupan, waktu cetak, kosong). */
    protected const HEADER_ROWS = 5;

    /**
     * @param  array{title: string, headings: list<string>, rows: list<list<mixed>>}  $sheet
     * @param  array<string, mixed>  $report
     * @param  list<string>  $scope
     */
    public function __construct(protected array $sheet, protected array $report, protected array $scope) {}

    public function array(): array
    {
        return [
            [setting('program.name').' — '.$this->report['title'].' ('.$this->sheet['title'].')'],
            ['Periode: '.$this->report['period']],
            ['Cakupan: '.($this->scope ? implode(' · ', $this->scope) : 'Semua')],
            ['Dicetak: '.tanggal_jam(now()).' WITA · '.setting('program.organizer')],
            [''],
            $this->sheet['headings'],
            ...$this->sheet['rows'],
        ];
    }

    public function title(): string
    {
        // Batas nama lembar Excel 31 karakter tanpa karakter khusus.
        return Str::limit(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->sheet['title']), 31, '');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle((self::HEADER_ROWS + 1).':'.(self::HEADER_ROWS + 1))->getFont()->setBold(true);
                $sheet->freezePane('A'.(self::HEADER_ROWS + 2));
            },
        ];
    }
}
