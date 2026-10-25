<?php

namespace App\Notifications;

class RatingReminderNotification extends WokabulariNotification
{
    protected string $icon = 'star';

    protected string $level = 'warning';

    protected bool $viaWhatsApp = true;

    public function __construct(public int $pending) {}

    public function title(): string
    {
        return 'Rating mingguan belum diisi';
    }

    public function body(): string
    {
        return "Masih ada {$this->pending} rating vendor/pengajar minggu ini yang belum ngana isi. Cuma 1 menit, bantu torang jadi lebih bae!";
    }

    public function url(): ?string
    {
        return route('ratings.index');
    }
}
