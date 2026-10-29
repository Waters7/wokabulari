<?php

namespace App\Services\System;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Pemakaian penyimpanan: disk VPS (total, terpakai, sisa) dan rincian yang dipakai Wokabulari
 * (database, berkas privat, berkas publik, cadangan, log). Disimpan 10 menit agar halaman ringan.
 */
class StorageUsage
{
    public const CACHE_KEY = 'system.storage-usage';

    /** Peringatan bila sisa disk VPS di bawah persentase ini. */
    public const LOW_FREE_PERCENT = 15;

    /**
     * @return array{disk: array{total: int, free: int, used: int, percent: float}|null, parts: array<string, array{label: string, bytes: ?int}>, total: int, measured_at: string}
     */
    public function summary(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, 600, fn () => $this->measure());
    }

    /**
     * @return array<string, mixed>
     */
    protected function measure(): array
    {
        $parts = [
            'database' => ['label' => 'Database', 'bytes' => $this->databaseSize()],
            'private' => ['label' => 'Berkas privat (selfie, tanda tangan, tugas, foto konsumsi)', 'bytes' => $this->directorySize(Storage::disk('private')->path(''))],
            'public' => ['label' => 'Berkas publik (materi, foto profil)', 'bytes' => $this->directorySize(Storage::disk('public')->path(''))],
            'backups' => ['label' => 'Cadangan dari aplikasi', 'bytes' => $this->directorySize(Storage::disk('backups')->path(''))],
            'logs' => ['label' => 'Log aplikasi', 'bytes' => $this->directorySize(storage_path('logs'))],
        ];

        return [
            'disk' => $this->disk(storage_path('app')),
            'parts' => $parts,
            'total' => (int) collect($parts)->sum(fn ($part) => $part['bytes'] ?? 0),
            'measured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Kapasitas disk tempat data aplikasi disimpan (di server = disk VPS tempat volume Docker berada).
     *
     * @return array{total: int, free: int, used: int, percent: float}|null
     */
    public function disk(string $path): ?array
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! $total || $free === false) {
            return null;
        }

        $used = (int) ($total - $free);

        return ['total' => (int) $total, 'free' => (int) $free, 'used' => $used, 'percent' => round($used / $total * 100, 1)];
    }

    public function databaseSize(): ?int
    {
        try {
            return match (DB::connection()->getDriverName()) {
                'mysql', 'mariadb' => (int) DB::selectOne('select coalesce(sum(data_length + index_length), 0) as size from information_schema.tables where table_schema = database()')->size,
                'sqlite' => is_file($path = (string) DB::connection()->getConfig('database')) ? (int) filesize($path) : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    public function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /** Mis. 1,5 GB. */
    public static function format(?int $bytes): string
    {
        if ($bytes === null) {
            return '–';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return angka($bytes / (1024 ** $power), $power >= 2 ? 1 : 0).' '.$units[$power];
    }
}
