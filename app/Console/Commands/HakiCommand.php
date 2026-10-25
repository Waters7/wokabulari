<?php

namespace App\Console\Commands;

use App\Support\Haki;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:haki {--berkas= : Berkas kunci rahasia HaKI untuk membuktikan kepemilikan & membuka segel}')]
#[Description('Periksa pernyataan HaKI yang tertanam di kode (tanda tangan Ed25519 & segel terenkripsi).')]
class HakiCommand extends Command
{
    public function handle(): int
    {
        $this->components->twoColumnDetail('<fg=gray>Pernyataan hak cipta</>');

        foreach (Haki::STATEMENT as $key => $value) {
            $this->components->twoColumnDetail(str_replace('_', ' ', ucfirst($key)), $value);
        }

        $this->components->twoColumnDetail('Sidik HaKI', Haki::fingerprint());

        if (! Haki::verify()) {
            $this->components->error('Tanda tangan HaKI TIDAK SAH: pernyataan diubah atau belum ditandatangani.');

            return self::FAILURE;
        }

        $this->components->info('Tanda tangan HaKI sah — pernyataan utuh sejak ditandatangani pemegang kunci.');

        if (! $path = $this->option('berkas')) {
            return self::SUCCESS;
        }

        $keys = self::readKeyFile($path);

        if ($keys === null) {
            $this->components->error("Berkas kunci tidak terbaca: {$path}");

            return self::FAILURE;
        }

        if (! Haki::ownsKey($keys['HAKI_SIGN_SECRET'] ?? '')) {
            $this->components->error('Kunci privat di berkas ini BUKAN pasangan kunci publik yang tertanam.');

            return self::FAILURE;
        }

        $this->components->info('Kunci privat cocok: pemegang berkas ini adalah pemilik HaKI yang tertanam.');

        $sealed = Haki::open($keys['HAKI_SEAL_KEY'] ?? '');

        if ($sealed === null) {
            $this->components->error('Segel tidak bisa dibuka dengan kunci segel di berkas ini.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=gray>Isi segel terenkripsi</>');

        foreach ($sealed as $key => $value) {
            $this->components->twoColumnDetail(str_replace('_', ' ', ucfirst((string) $key)), (string) $value);
        }

        return self::SUCCESS;
    }

    /**
     * Baca berkas KUNCI=nilai (baris lain diabaikan).
     *
     * @return array<string, string>|null
     */
    public static function readKeyFile(string $path): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^(HAKI_[A-Z_]+)=(\S+)$/', trim($line), $m)) {
                $keys[$m[1]] = $m[2];
            }
        }

        return $keys;
    }
}
