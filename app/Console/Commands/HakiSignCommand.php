<?php

namespace App\Console\Commands;

use App\Support\Haki;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Signature('app:haki-sign
    {--berkas=.rahasia/HAKI-KUNCI-RAHASIA.txt : Tempat menyimpan kunci rahasia (di luar git & image Docker)}
    {--kontak= : Kontak pencipta yang ikut disegel (bawaan: email git)}
    {--force : Timpa berkas kunci yang sudah ada (kunci lama tidak berlaku lagi)}')]
#[Description('Tandatangani & segel pernyataan HaKI di app/Support/Haki.php dengan pasangan kunci baru (hanya di lingkungan pengembangan).')]
class HakiSignCommand extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Penandatanganan HaKI hanya di laptop pengembang, bukan di server produksi.');

            return self::FAILURE;
        }

        $path = base_path($this->option('berkas'));

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("Berkas kunci sudah ada ({$this->option('berkas')}). Pakai --force hanya bila memang ingin mengganti kunci.");

            return self::FAILURE;
        }

        $contact = $this->option('kontak') ?: trim(Process::run('git config user.email')->output());
        $commit = trim(Process::run('git rev-parse HEAD')->output());

        $issued = Haki::issue([
            'kontak_pencipta' => $contact ?: '-',
            'versi_aplikasi' => config('wokabulari.version'),
            'commit_acuan' => $commit ?: '-',
            'ditandatangani_pada' => now()->toIso8601String(),
        ]);

        $source = app_path('Support/Haki.php');
        $code = file_get_contents($source);

        foreach (['PUBLIC_KEY' => 'public_key', 'SEALED' => 'sealed', 'SIGNATURE' => 'signature'] as $const => $field) {
            $code = preg_replace("/public const {$const} = '[^']*';/", "public const {$const} = '{$issued[$field]}';", $code, 1, $count);

            if ($count !== 1) {
                $this->components->error("Konstanta {$const} tidak ditemukan di app/Support/Haki.php.");

                return self::FAILURE;
            }
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            '# KUNCI RAHASIA HaKI WOKABULARI — JANGAN DIBAGIKAN, JANGAN DI-COMMIT, TIDAK PERLU DI SERVER.',
            '# Simpan salinan di tempat aman (flashdisk/brankas kata sandi) milik pemegang hak cipta.',
            '# Bukti kepemilikan: php artisan app:haki --berkas=<berkas ini>',
            '# Dibuat: '.now()->toIso8601String().' · Sidik: '.Haki::fingerprint($issued['public_key'], $issued['signature']),
            'HAKI_SIGN_SECRET='.$issued['sign_secret'],
            'HAKI_SEAL_KEY='.$issued['seal_key'],
            'HAKI_PUBLIC_KEY='.$issued['public_key'],
            '',
        ]));
        @chmod($path, 0600);

        file_put_contents($source, $code);

        $this->components->info('Pernyataan HaKI ditandatangani. Sidik: '.Haki::fingerprint($issued['public_key'], $issued['signature']));
        $this->components->warn("Kunci rahasia disimpan di {$this->option('berkas')} — salin ke tempat aman, jangan dibagikan.");

        return self::SUCCESS;
    }
}
