<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

/**
 * Kompres & simpan gambar (selfie, tanda tangan, foto keluhan) dengan nama UUID.
 */
class ImageService
{
    protected const MAX_DATA_URI_BYTES = 3 * 1024 * 1024;

    protected function manager(): ImageManager
    {
        return ImageManager::usingDriver(Driver::class);
    }

    /**
     * Simpan data URI JPEG/PNG (dari kamera browser) sebagai JPEG terkompresi.
     */
    public function storeDataUri(string $dataUri, string $directory, int $maxWidth = 480, int $quality = 72, string $disk = 'private'): string
    {
        $image = $this->decodeDataUri($dataUri, ['image/jpeg', 'image/png', 'image/webp']);

        return $this->storeJpeg($image->orient()->scaleDown($maxWidth), $directory, $quality, $disk);
    }

    /**
     * Simpan tanda tangan (PNG transparan) apa adanya setelah divalidasi.
     */
    public function storeSignature(string $dataUri, string $directory, string $disk = 'private'): string
    {
        $image = $this->decodeDataUri($dataUri, ['image/png']);
        $path = trim($directory, '/').'/'.Str::uuid().'.png';
        Storage::disk($disk)->put($path, (string) $image->scaleDown(800)->encode(new PngEncoder));

        return $path;
    }

    /**
     * Simpan unggahan gambar (foto keluhan, foto tugas) dengan kompresi.
     */
    public function storeUpload(UploadedFile $file, string $directory, int $maxWidth = 1600, int $quality = 80, string $disk = 'private'): string
    {
        $image = $this->manager()->decodePath($file->getRealPath());

        return $this->storeJpeg($image->orient()->scaleDown($maxWidth), $directory, $quality, $disk);
    }

    /**
     * @param  list<string>  $allowedMimes
     */
    protected function decodeDataUri(string $dataUri, array $allowedMimes): ImageInterface
    {
        if (! preg_match('#^data:(image/[a-z+]+);base64,#', $dataUri, $matches) || ! in_array($matches[1], $allowedMimes, true)) {
            throw new InvalidArgumentException('Format gambar tidak valid.');
        }

        $binary = base64_decode(substr($dataUri, strlen($matches[0])), true);

        if ($binary === false || strlen($binary) > self::MAX_DATA_URI_BYTES) {
            throw new InvalidArgumentException('Ukuran gambar terlalu besar atau rusak.');
        }

        // Pastikan isi benar-benar gambar sesuai MIME (bukan sekadar header).
        $realMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if (! in_array($realMime, $allowedMimes, true)) {
            throw new InvalidArgumentException('Isi berkas bukan gambar yang diizinkan.');
        }

        return $this->manager()->decodeBinary($binary);
    }

    protected function storeJpeg(ImageInterface $image, string $directory, int $quality, string $disk): string
    {
        $path = trim($directory, '/').'/'.Str::uuid().'.jpg';
        Storage::disk($disk)->put($path, (string) $image->encode(new JpegEncoder(quality: $quality)));

        return $path;
    }
}
