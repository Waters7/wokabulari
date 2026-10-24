<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca baris Excel/CSV peserta apa adanya; pemrosesan di ImportParticipants.
 */
class ParticipantRowsImport implements ToCollection, WithHeadingRow
{
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
