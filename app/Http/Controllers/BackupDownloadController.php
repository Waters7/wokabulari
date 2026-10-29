<?php

namespace App\Http\Controllers;

use App\Services\System\BackupService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh cadangan dari aplikasi (hanya pengelola pengaturan sistem; berisi data pribadi).
 */
class BackupDownloadController extends Controller
{
    public function __invoke(string $name, BackupService $backups): BinaryFileResponse
    {
        try {
            $path = $backups->path($name);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        activity('sistem')->causedBy(auth()->user())->withProperties(['berkas' => $name])->log('Mengunduh cadangan');

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }
}
