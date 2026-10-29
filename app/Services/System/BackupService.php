<?php

namespace App\Services\System;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;
use ZipArchive;

/**
 * Cadangan dari aplikasi (tombol di Pengaturan Sistem): satu berkas ZIP berisi dump database (SQL)
 * dan berkas privat & publik, disimpan di disk "backups" (storage/app/backups, tidak publik).
 * Cadangan harian otomatis tetap lewat scripts/backup.sh di server.
 */
class BackupService
{
    public const STATUS_KEY = 'system.backup-status';

    /** Jumlah cadangan dari aplikasi yang disimpan; yang lebih lama dihapus otomatis. */
    public const KEEP = 5;

    public const NAME_PATTERN = '/^wokabulari-\d{8}-\d{6}\.zip$/';

    /** Tabel sementara: struktur ikut, isinya tidak. */
    protected const SKIP_DATA = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function __construct(protected StorageUsage $usage) {}

    /**
     * Buat cadangan baru dan kembalikan nama berkasnya.
     */
    public function create(): string
    {
        $disk = Storage::disk('backups');
        $root = $disk->path('');
        is_dir($root) || mkdir($root, 0700, true);

        $estimate = (int) ($this->usage->databaseSize() ?? 0)
            + $this->usage->directorySize(Storage::disk('private')->path(''))
            + $this->usage->directorySize(Storage::disk('public')->path(''));
        $free = @disk_free_space($root);
        if ($free !== false && $free < $estimate * 1.1 + 100 * 1024 * 1024) {
            throw new RuntimeException('Ruang disk tidak cukup untuk membuat cadangan (perlu ± '.StorageUsage::format((int) ($estimate * 1.1)).', sisa '.StorageUsage::format((int) $free).').');
        }

        $name = 'wokabulari-'.now()->format('Ymd-His').'.zip';
        $sql = tempnam(sys_get_temp_dir(), 'woka-sql');
        $this->dumpDatabase($sql);

        $zip = new ZipArchive;
        if ($zip->open($disk->path($name), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas cadangan tidak bisa dibuat.');
        }

        $zip->addFile($sql, 'database.sql');
        $this->addDirectory($zip, rtrim(Storage::disk('private')->path(''), '/\\'), 'storage/private', ['livewire-tmp']);
        $this->addDirectory($zip, rtrim(Storage::disk('public')->path(''), '/\\'), 'storage/public');
        $zip->addFromString('BACA-SAYA.txt', $this->readme());
        $zip->close();
        @unlink($sql);
        @chmod($disk->path($name), 0600);

        return $name;
    }

    /**
     * Dump SQL lengkap (struktur + isi) memakai koneksi aplikasi, tanpa butuh mysqldump.
     */
    public function dumpDatabase(string $file): void
    {
        $out = fopen($file, 'wb');
        $pdo = DB::connection()->getPdo();
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

        fwrite($out, '-- Wokabulari '.config('wokabulari.version').' · '.now()->toIso8601String()."\n");
        fwrite($out, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

        foreach ($this->tables($mysql) as $table => $create) {
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            if (in_array($table, self::SKIP_DATA, true)) {
                continue;
            }

            $batch = [];
            $columns = null;
            foreach (DB::table($table)->cursor() as $row) {
                $row = (array) $row;
                $columns ??= '`'.implode('`, `', array_keys($row)).'`';
                $batch[] = '('.implode(', ', array_map(fn ($value) => match (true) {
                    $value === null => 'NULL',
                    is_bool($value) => $value ? '1' : '0',
                    is_int($value), is_float($value) => (string) $value,
                    default => $pdo->quote((string) $value),
                }, $row)).')';

                if (count($batch) === 200) {
                    fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch !== []) {
                fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
            }
            fwrite($out, "\n");
        }

        fwrite($out, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
        fclose($out);
    }

    /**
     * @return Collection<int, array{name: string, size: int, created_at: Carbon}>
     */
    public function list(): Collection
    {
        $disk = Storage::disk('backups');

        return collect($disk->files())
            ->filter(fn (string $file) => preg_match(self::NAME_PATTERN, $file))
            ->map(fn (string $file) => ['name' => $file, 'size' => (int) $disk->size($file), 'created_at' => Carbon::createFromTimestamp($disk->lastModified($file))->timezone(config('app.timezone'))])
            ->sortByDesc('name')->values();
    }

    /** Sisakan KEEP cadangan terbaru. */
    public function prune(): int
    {
        return $this->list()->slice(self::KEEP)->each(fn (array $backup) => $this->delete($backup['name']))->count();
    }

    public function delete(string $name): void
    {
        Storage::disk('backups')->delete($this->validName($name));
    }

    public function path(string $name): string
    {
        $name = $this->validName($name);
        if (! Storage::disk('backups')->exists($name)) {
            throw new InvalidArgumentException('Cadangan tidak ditemukan.');
        }

        return Storage::disk('backups')->path($name);
    }

    /** @return array<string, mixed>|null */
    public function status(): ?array
    {
        return Cache::get(self::STATUS_KEY);
    }

    /** @param array<string, mixed> $status */
    public function setStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addDays(7));
    }

    /** Sedang antre/berjalan (dianggap macet setelah 2 jam). */
    public function busy(): bool
    {
        $status = $this->status();

        return in_array($status['state'] ?? null, ['queued', 'running'], true)
            && Carbon::parse($status['queued_at'] ?? now())->gt(now()->subHours(2));
    }

    protected function validName(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidArgumentException('Nama berkas cadangan tidak valid.');
        }

        return $name;
    }

    /**
     * @return array<string, string> nama tabel => CREATE TABLE
     */
    protected function tables(bool $mysql): array
    {
        if ($mysql) {
            return collect(DB::select("show full tables where Table_type = 'BASE TABLE'"))
                ->map(fn ($row) => array_values((array) $row)[0])
                ->mapWithKeys(fn (string $table) => [$table => (array) DB::selectOne("show create table `{$table}`")])
                ->map(fn (array $row) => $row['Create Table'])->all();
        }

        return collect(DB::select("select name, sql from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"))
            ->mapWithKeys(fn ($row) => [$row->name => $row->sql])->all();
    }

    /**
     * @param  list<string>  $skip  nama folder tingkat atas yang dilewati
     */
    protected function addDirectory(ZipArchive $zip, string $path, string $prefix, array $skip = []): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($path) + 1));
            if (in_array(explode('/', $relative)[0], $skip, true) || str_starts_with($relative, '.')) {
                continue;
            }

            $zip->addFile($file->getPathname(), $prefix.'/'.$relative);
        }
    }

    protected function readme(): string
    {
        return implode("\n", [
            'Cadangan Wokabulari '.config('wokabulari.version').' — dibuat '.now()->format('d-m-Y H:i').' WITA.',
            '',
            'Isi:',
            '- database.sql      : struktur & isi database (tanpa cache/sesi/antrean).',
            '- storage/private/  : selfie, tanda tangan, tugas, lampiran, foto konsumsi (data pribadi — simpan aman).',
            '- storage/public/   : materi & foto profil.',
            '',
            'Pemulihan (di server, folder /opt/wokabulari):',
            '1. Impor database : docker compose -f compose.prod.yaml exec -T db sh -c \'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"\' < database.sql',
            '2. Salin berkas   : isi storage/private dan storage/public ke volume storage-app (storage/app/private dan storage/app/public).',
            '3. Jalankan       : bash scripts/deploy.sh',
            '',
            'Berkas ini berisi data pribadi peserta (UU PDP). Jangan dibagikan; simpan di penyimpanan terenkripsi.',
        ])."\n";
    }
}
