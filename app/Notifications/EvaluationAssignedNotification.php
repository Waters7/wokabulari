<?php

namespace App\Notifications;

use App\Models\ProgramEvaluation;

/**
 * Survei baru atau pengingat menjelang tenggat.
 */
class EvaluationAssignedNotification extends WokabulariNotification
{
    public function __construct(public ProgramEvaluation $evaluation, public bool $reminder = false)
    {
        $this->icon = $evaluation->category->icon();
        $this->level = $reminder ? 'warning' : 'info';
        $this->viaWhatsApp = true;
    }

    public function title(): string
    {
        return ($this->reminder ? 'Pengingat survei: ' : 'Survei baru: ').$this->evaluation->title;
    }

    public function body(): string
    {
        return 'Isi sebelum '.tanggal_jam($this->evaluation->closes_at).' WITA. '.($this->evaluation->is_anonymous ? 'Jawaban ngana anonim.' : '');
    }

    public function url(): ?string
    {
        return route('evaluations.fill', $this->evaluation);
    }
}
