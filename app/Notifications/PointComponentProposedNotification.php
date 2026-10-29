<?php

namespace App\Notifications;

use App\Models\GamificationRule;

/**
 * Pengajar mengajukan komponen poin tambahan; admin perlu meninjau.
 */
class PointComponentProposedNotification extends WokabulariNotification
{
    public function __construct(public GamificationRule $component)
    {
        $this->icon = 'sparkles';
        $this->level = 'warning';
    }

    public function title(): string
    {
        return 'Pengajuan komponen poin: '.$this->component->name;
    }

    public function body(): string
    {
        return ($this->component->proposer?->name ?? 'Pengajar').' mengajukan '.$this->component->pointsLabel().' poin ('.$this->component->category->label().'). Tinjau di Komponen Poin.';
    }

    public function url(): ?string
    {
        return route('master.point-components');
    }
}
