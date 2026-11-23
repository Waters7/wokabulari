<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

/**
 * Kop surat Kemenkes Poltekkes Manado untuk dokumen cetak (PDF laporan, rapor, lembar quiz):
 * logo di kiri dan identitas instansi di kanan.
 */
final class Letterhead
{
    public const LOGO = 'images/brand/kop-logo.png';

    public const INSTITUTION = 'images/brand/kop-instansi.png';

    /**
     * Gambar kop sebagai data URI (dompdf tidak mengambil berkas dari luar).
     *
     * @return array{logo: ?string, institution: ?string}
     */
    public static function pdf(): array
    {
        return ['logo' => self::dataUri(self::LOGO), 'institution' => self::dataUri(self::INSTITUTION)];
    }

    /**
     * Alamat gambar kop untuk halaman HTML.
     *
     * @return array{logo: string, institution: string}
     */
    public static function urls(): array
    {
        return ['logo' => asset(self::LOGO), 'institution' => asset(self::INSTITUTION)];
    }

    /**
     * Render PDF lalu tulis "Halaman X dari Y" di pojok kanan bawah setiap halaman
     * (dompdf tidak mendukung jumlah halaman lewat CSS). Huruf yang dipakai saja yang disematkan.
     */
    public static function output(PDF $pdf): string
    {
        $pdf->setOption('isFontSubsettingEnabled', true);
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $size = 7;
        $width = $metrics->getTextWidth('Halaman 99 dari 99', $font, $size);

        $canvas->page_text($canvas->get_width() - 22 - $width, $canvas->get_height() - 24, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $font, $size, [0.36, 0.45, 0.45]);

        return $pdf->output();
    }

    protected static function dataUri(string $path): ?string
    {
        $file = public_path($path);

        return is_file($file) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($file)) : null;
    }
}
