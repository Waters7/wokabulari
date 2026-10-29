<?php

namespace App\Notifications;

use App\Services\System\StorageUsage;

/**
 * Hasil tombol "Buat cadangan sekarang".
 */
class BackupFinishedNotification extends WokabulariNotification
{
    public function __construct(public bool $success, public ?string $file = null, public ?int $size = null, public ?string $message = null)
    {
        $this->icon = $success ? 'archive-box-arrow-down' : 'exclamation-triangle';
        $this->level = $success ? 'success' : 'danger';
    }

    public function title(): string
    {
        return $this->success ? 'Cadangan siap diunduh' : 'Cadangan gagal dibuat';
    }

    public function body(): string
    {
        return $this->success
            ? $this->file.' ('.StorageUsage::format($this->size).'). Unduh dari Pengaturan Sistem dan simpan di luar server.'
            : (string) $this->message;
    }

    public function url(): ?string
    {
        return route('system.settings');
    }
}
