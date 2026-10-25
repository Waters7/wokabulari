<?php

namespace App\Notifications;

use App\Models\Level;
use Illuminate\Support\Facades\Route;

class LevelUpNotification extends WokabulariNotification
{
    protected string $icon = 'sparkles';

    protected string $level = 'success';

    public function __construct(public Level $newLevel) {}

    public function title(): string
    {
        return 'Naik level: '.$this->newLevel->name.'!';
    }

    public function body(): string
    {
        return 'Ngana so mencapai level '.$this->newLevel->name.' ('.angka($this->newLevel->min_points).' poin). Torang Go Global!';
    }

    public function url(): ?string
    {
        return Route::has('points.index') ? route('points.index') : null;
    }
}
