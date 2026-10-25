<?php

namespace App\Console\Commands;

use App\Actions\Announcements\ManageAnnouncements;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:publish-announcements')]
#[Description('Kirim notifikasi pengumuman terjadwal yang sudah tiba waktunya terbit.')]
class PublishAnnouncements extends Command
{
    public function handle(ManageAnnouncements $announcements): int
    {
        $this->info($announcements->publishDue().' notifikasi pengumuman dikirim.');

        return self::SUCCESS;
    }
}
