<?php

namespace App\Console\Commands;

use App\Actions\Dorm\ManageExitPermits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-late-returns')]
#[Description('Tandai izin keluar yang melewati rencana kembali sebagai terlambat dan kabari pengelola & wali.')]
class CheckLateReturns extends Command
{
    public function handle(ManageExitPermits $permits): int
    {
        $count = $permits->markLateReturns();
        $this->info("{$count} izin ditandai terlambat kembali.");

        return self::SUCCESS;
    }
}
