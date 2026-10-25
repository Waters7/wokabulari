<?php

namespace App\Console\Commands;

use App\Services\DormRoster;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:close-dorm-night {date? : Tanggal malam yang ditutup (bawaan: kemarin)}')]
#[Description('Tutup absen asrama satu malam: yang belum tercatat menjadi tidak di asrama / izin keluar / sakit di kamar.')]
class CloseDormNight extends Command
{
    public function handle(DormRoster $roster): int
    {
        $date = $this->argument('date') ? CarbonImmutable::parse($this->argument('date')) : CarbonImmutable::yesterday();
        $count = $roster->closeNight($date);
        $this->info("{$count} catatan absen asrama dibuat untuk ".$date->toDateString().'.');

        return self::SUCCESS;
    }
}
