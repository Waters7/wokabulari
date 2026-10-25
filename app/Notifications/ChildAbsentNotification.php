<?php

namespace App\Notifications;

use App\Models\LearningSession;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Ke wali: anak tercatat alpa pada sesi kelas.
 */
class ChildAbsentNotification extends WokabulariNotification
{
    protected string $icon = 'calendar-days';

    protected string $level = 'warning';

    protected bool $viaWhatsApp = true;

    public function __construct(public User $participant, public LearningSession $session) {}

    public function title(): string
    {
        return $this->participant->name.' tidak hadir di kelas';
    }

    public function body(): string
    {
        return 'Sesi "'.$this->session->topic.'" '.tanggal($this->session->date, 'l, j F').' pukul '.$this->session->start_time.' WITA tercatat alpa (tanpa izin).';
    }

    public function url(): ?string
    {
        return Route::has('guardian.attendance') ? route('guardian.attendance', ['anak' => $this->participant->id]) : null;
    }
}
