<?php

namespace App\Services;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Pembuat QR code SVG (kode sesi, izin keluar asrama, verifikasi sertifikat).
 */
class QrCodeService
{
    public function svg(string $content, int $size = 240, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(11, 59, 56))),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($content);

        // Buang deklarasi XML agar aman disisipkan langsung di HTML.
        return trim(preg_replace('/^<\?xml.*?\?>/', '', $svg));
    }

    /** Data URI untuk <img> (dipakai di PDF dompdf). */
    public function dataUri(string $content, int $size = 240): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($content, $size));
    }
}
