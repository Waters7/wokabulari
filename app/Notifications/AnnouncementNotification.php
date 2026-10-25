<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Support\Str;

/**
 * Pengumuman baru; judul diawali sumbernya (Tim Kerja / Pengajar / Asrama).
 */
class AnnouncementNotification extends WokabulariNotification
{
    public function __construct(public Announcement $announcement)
    {
        $this->icon = $announcement->source->icon();
        $this->level = $announcement->is_pinned ? 'warning' : 'info';
        $this->viaWhatsApp = $announcement->notify_whatsapp;
    }

    public function title(): string
    {
        return 'Pengumuman '.$this->announcement->source->label().': '.$this->announcement->title;
    }

    public function body(): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->announcement->body), 160);
    }

    public function url(): ?string
    {
        return route('announcements.index', ['buka' => $this->announcement->id]);
    }
}
