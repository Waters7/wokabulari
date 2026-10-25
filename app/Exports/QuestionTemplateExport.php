<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['tipe', 'soal', 'bacaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'jawaban', 'poin', 'pembahasan'];
    }

    public function collection(): Enumerable
    {
        return new Collection([
            ['pg', '你好吗？ artinya…', 'nǐ hǎo ma?', 'Apa kabar?', 'Siapa namamu?', 'Selamat pagi', 'Terima kasih', '', 'a', 1, '你好吗 = apa kabar'],
            ['bs', 'Nurse artinya perawat.', '', '', '', '', '', '', 'benar', 1, ''],
            ['isian', 'Bahasa Inggris dari "dokter" adalah …', '', '', '', '', '', '', 'doctor|a doctor', 1, ''],
            ['jodoh', 'Jodohkan kata berikut.', '', '', '', '', '', '', '医生=dokter|护士=perawat|病人=pasien', 3, ''],
            ['nada', '妈', 'ma', '', '', '', '', '', '1', 1, '妈 (mā) = ibu, nada 1'],
            ['esai', 'Ceritakan pengalamanmu merawat pasien (min. 5 kalimat).', '', '', '', '', '', '', '', 5, ''],
        ]);
    }
}
