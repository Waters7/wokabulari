<?php

namespace App\Exports;

use App\Models\Language;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Templat import Bank Kosakata: lembar "Kosakata" (judul kolom + contoh sesuai bahasa) dan lembar "Petunjuk".
 */
class VocabularyTemplateExport implements Export, WithMultipleSheets
{
    use Exportable;

    public const HEADINGS = ['kata', 'cara_baca', 'arti', 'topik', 'contoh', 'arti_contoh', 'urutan', 'aktif'];

    public function __construct(protected ?Language $language = null) {}

    public function sheets(): array
    {
        $examples = $this->examples();
        $instructions = [
            ['kata', 'Wajib. Kata dalam bahasa asing, mis. 医生 / 医者 / der Arzt / doctor.'],
            ['cara_baca', 'Opsional. '.($this->language?->reading_label ?? 'Cara baca').', mis. yīshēng / いしゃ (isha) / /ˈdɒktə/.'],
            ['arti', 'Wajib. Arti dalam Bahasa Indonesia.'],
            ['topik', 'Opsional. Kelompok kata, mis. Kesehatan, Sapaan, Angka & waktu.'],
            ['contoh', 'Opsional. Contoh kalimat dalam bahasa asing.'],
            ['arti_contoh', 'Opsional. Arti contoh kalimat.'],
            ['urutan', 'Opsional. Angka urutan tampil di kartu harian (kecil tampil lebih dulu). Kosong = di akhir bank.'],
            ['aktif', 'Opsional. "ya" atau "tidak". Kosong = ya. Kata nonaktif tidak muncul di kartu harian.'],
            ['', ''],
            ['Catatan', 'Baris contoh boleh ditimpa atau dihapus. Kata yang sudah ada di bank bahasa ini (tulisan kata sama) akan diperbarui, bukan digandakan.'],
            ['Bahasa', 'Bahasa dipilih saat import di halaman Bank Kosakata'.($this->language ? ' (templat ini: '.$this->language->name.')' : '').'.'],
        ];

        return [
            new class(self::HEADINGS, $examples, 'Kosakata') implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function __construct(private array $headings, private array $rows, private string $title) {}

                public function headings(): array
                {
                    return $this->headings;
                }

                public function array(): array
                {
                    return $this->rows;
                }

                public function title(): string
                {
                    return $this->title;
                }
            },
            new class(['kolom', 'keterangan'], $instructions, 'Petunjuk') implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
            {
                public function __construct(private array $headings, private array $rows, private string $title) {}

                public function headings(): array
                {
                    return $this->headings;
                }

                public function array(): array
                {
                    return $this->rows;
                }

                public function title(): string
                {
                    return $this->title;
                }
            },
        ];
    }

    /** @return list<list<string|int>> */
    protected function examples(): array
    {
        return match ($this->language?->code) {
            'zh' => [
                ['医生', 'yīshēng', 'Dokter', 'Kesehatan', '他是医生。', 'Dia seorang dokter.', 1, 'ya'],
                ['护士', 'hùshi', 'Perawat', 'Kesehatan', '我是护士。', 'Saya seorang perawat.', 2, 'ya'],
                ['谢谢', 'xièxie', 'Terima kasih', 'Sapaan', '', '', 3, 'ya'],
            ],
            'ja' => [
                ['医者', 'いしゃ (isha)', 'Dokter', 'Kesehatan', '', '', 1, 'ya'],
                ['看護師', 'かんごし (kangoshi)', 'Perawat', 'Kesehatan', '', '', 2, 'ya'],
                ['ありがとう', 'arigatō', 'Terima kasih', 'Sapaan', '', '', 3, 'ya'],
            ],
            'de' => [
                ['der Arzt', '', 'Dokter (laki-laki)', 'Kesehatan', '', '', 1, 'ya'],
                ['die Pflegekraft', '', 'Tenaga perawat', 'Kesehatan', '', '', 2, 'ya'],
                ['danke', '', 'Terima kasih', 'Sapaan', '', '', 3, 'ya'],
            ],
            default => [
                ['doctor', '/ˈdɒktə/', 'Dokter', 'Kesehatan', 'She is a doctor.', 'Dia seorang dokter.', 1, 'ya'],
                ['nurse', '/nɜːs/', 'Perawat', 'Kesehatan', '', '', 2, 'ya'],
                ['thank you', '', 'Terima kasih', 'Sapaan', '', '', 3, 'ya'],
            ],
        };
    }
}
