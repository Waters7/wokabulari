<?php

namespace App\Notifications;

use App\Models\Assessment;
use Illuminate\Support\Facades\Route;

/**
 * Ke wali: nilai bulanan anak sudah terbit.
 */
class GuardianAssessmentNotification extends WokabulariNotification
{
    protected string $icon = 'chart-bar';

    protected string $level = 'success';

    protected bool $viaWhatsApp = true;

    public function __construct(public Assessment $assessment) {}

    public function title(): string
    {
        return 'Nilai bulanan '.$this->assessment->user->name.' terbit';
    }

    public function body(): string
    {
        return 'Rata-rata '.angka($this->assessment->average).' (predikat '.$this->assessment->grade.'). Lihat masukan pengajar di portal wali.';
    }

    public function url(): ?string
    {
        return Route::has('guardian.grades') ? route('guardian.grades', ['anak' => $this->assessment->user_id]) : null;
    }
}
