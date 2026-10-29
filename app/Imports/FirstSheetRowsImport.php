<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Membaca baris lembar pertama saja (lembar lain, mis. "Petunjuk" di templat, diabaikan).
 */
class FirstSheetRowsImport implements Import, WithMultipleSheets
{
    public ParticipantRowsImport $sheet;

    public function __construct()
    {
        $this->sheet = new ParticipantRowsImport;
    }

    public function sheets(): array
    {
        return [0 => $this->sheet];
    }

    public function rows(): Collection
    {
        return $this->sheet->rows;
    }
}
