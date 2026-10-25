<?php

namespace App\Notifications;

use App\Models\Badge;
use Illuminate\Support\Facades\Route;

class BadgeEarnedNotification extends WokabulariNotification
{
    protected string $icon = 'trophy';

    protected string $level = 'success';

    public function __construct(public Badge $badge) {}

    public function title(): string
    {
        return 'Lencana baru: '.$this->badge->name;
    }

    public function body(): string
    {
        return ($this->badge->description ?? '').' Mantap, pertahankan!';
    }

    public function url(): ?string
    {
        return Route::has('points.index') ? route('points.index') : null;
    }
}
