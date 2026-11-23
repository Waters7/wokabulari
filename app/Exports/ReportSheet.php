<?php

namespace App\Exports;

use App\Models\SessionDocumentation;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ReportSheet implements FromArray, ShouldAutoSize, WithDrawings, WithEvents, WithTitle
{
    /** Baris kepala sebelum judul kolom (judul, periode, cakupan, waktu cetak, kosong). */
    protected const HEADER_ROWS = 5;

    /** Tinggi foto yang disematkan (piksel). */
    public const IMAGE_HEIGHT = 90;

    /**
     * @param  array{title: string, headings: list<string>, rows: list<list<mixed>>, links?: list<int>, images?: list<array{row: int, column: int, doc?: SessionDocumentation, thumbnail?: callable(): ?string}>}  $sheet
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

    /**
     * Foto dokumentasi disematkan sebagai gambar kecil di sel masing-masing.
     *
     * @return list<Drawing>
     */
    public function drawings(): array
    {
        $drawings = [];

        foreach ($this->sheet['images'] ?? [] as $image) {
            $path = isset($image['thumbnail']) ? ($image['thumbnail'])() : $image['doc']->thumbnailPath();
            if (! $path || ! is_file($path)) {
                continue;
            }

            $drawing = new Drawing;
            $drawing->setName('Dokumentasi')->setPath($path)->setHeight(self::IMAGE_HEIGHT)
                ->setCoordinates($this->cell($image['column'], $image['row']))
                ->setOffsetX(4)->setOffsetY(4);
            $drawings[] = $drawing;
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle((self::HEADER_ROWS + 1).':'.(self::HEADER_ROWS + 1))->getFont()->setBold(true);
                $sheet->freezePane('A'.(self::HEADER_ROWS + 2));

                // Kolom tautan (mis. ke halaman sesi): tampil "Buka sesi" yang bisa diklik.
                foreach ($this->sheet['links'] ?? [] as $column) {
                    foreach ($this->sheet['rows'] as $i => $row) {
                        $url = $row[$column] ?? null;
                        if (is_string($url) && str_starts_with($url, 'http')) {
                            $cell = $sheet->getCell($this->cell($column, $i));
                            $cell->setValue('Buka sesi');
                            $cell->getHyperlink()->setUrl($url);
                            $cell->getStyle()->getFont()->setUnderline(true)->getColor()->setRGB('007D71');
                        }
                    }
                }

                // Ruang untuk foto: kolom foto selebar gambar, baris setinggi gambar.
                $images = $this->sheet['images'] ?? [];
                foreach (array_unique(array_column($images, 'column')) as $column) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column + 1))->setAutoSize(false)->setWidth(20);
                }
                foreach (array_unique(array_column($images, 'row')) as $row) {
                    $sheet->getRowDimension(self::HEADER_ROWS + 2 + $row)->setRowHeight((self::IMAGE_HEIGHT + 8) * 0.75);
                }
            },
        ];
    }

    /** Alamat sel data (kolom & baris dihitung dari 0, setelah baris kepala dan judul kolom). */
    protected function cell(int $column, int $row): string
    {
        return Coordinate::stringFromColumnIndex($column + 1).(self::HEADER_ROWS + 2 + $row);
    }
}
