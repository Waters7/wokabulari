<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\BackupFinishedNotification;
use App\Services\System\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Buat cadangan di antrean (tombol "Buat cadangan sekarang"), lalu beri tahu yang memintanya.
 */
class CreateBackup implements ShouldQueue
{
    use Queueable;

    /** Cadangan bisa lama bila berkas selfie/tugas banyak. */
    public int $timeout = 1800;

    public function __construct(public string $token, public ?int $requestedBy = null)
    {
        $this->onQueue('exports');
    }

    public function handle(BackupService $backups): void
    {
        $status = $backups->status() ?? [];

        // Salinan ulang dari antrean untuk cadangan yang sama (mis. melewati retry_after): abaikan.
        if (($status['token'] ?? null) !== $this->token || ($status['state'] ?? null) !== 'queued') {
            return;
        }

        $backups->setStatus(['state' => 'running', 'started_at' => now()->toIso8601String()] + $status);
        $requester = $this->requestedBy ? User::query()->find($this->requestedBy) : null;

        try {
            $name = $backups->create();
            $pruned = $backups->prune();
            $size = $backups->list()->firstWhere('name', $name)['size'] ?? 0;
            $backups->setStatus(['state' => 'done', 'finished_at' => now()->toIso8601String(), 'file' => $name, 'size' => $size, 'pruned' => $pruned] + $status);
            activity('sistem')->causedBy($requester)->withProperties(['berkas' => $name, 'ukuran' => $size])->log('Membuat cadangan dari aplikasi');
            $requester?->notify(new BackupFinishedNotification(true, $name, $size));
        } catch (Throwable $e) {
            report($e);
            $backups->setStatus(['state' => 'failed', 'finished_at' => now()->toIso8601String(), 'message' => $e->getMessage()] + $status);
            $requester?->notify(new BackupFinishedNotification(false, message: $e->getMessage()));
        }
    }
}
