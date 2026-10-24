<?php

namespace App\Console\Commands;

use App\Actions\Sessions\ManageSessionCode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:close-expired-codes')]
#[Description('Nonaktifkan kode kehadiran sesi yang sudah kedaluwarsa.')]
class CloseExpiredSessionCodes extends Command
{
    public function handle(): int
    {
        $count = ManageSessionCode::closeExpired();
        $this->info("{$count} kode dinonaktifkan.");

        return self::SUCCESS;
    }
}
