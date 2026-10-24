<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ParticipantTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return [
            'nama', 'no_hp', 'nis', 'email', 'asal_sekolah', 'jurusan', 'jenis_kelamin', 'tanggal_lahir',
            'kelas', 'alergi', 'riwayat_penyakit', 'kontak_darurat_nama', 'kontak_darurat_hp',
            'target_ujian', 'target_skor', 'keterangan',
        ];
    }

    public function collection(): Enumerable
    {
        return new Collection([[
            'Contoh Nama Peserta', "'081234567890", '', 'contoh@email.com', 'SMK Negeri 1 Manado', 'Keperawatan', 'P', '17/08/2007',
            'Kelas Inggris A', 'Udang', '', 'Nama Orang Tua', "'081298765432", 'IELTS', '5.5', 'Baris contoh, hapus sebelum import',
        ]]);
    }
}
