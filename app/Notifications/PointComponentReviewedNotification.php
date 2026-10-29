<?php

namespace App\Notifications;

use App\Enums\ReviewStatus;
use App\Models\GamificationRule;

/**
 * Hasil tinjauan komponen poin yang diajukan pengajar.
 */
class PointComponentReviewedNotification extends WokabulariNotification
{
    public function __construct(public GamificationRule $component)
    {
        $approved = $component->status === ReviewStatus::Approved;
        $this->icon = $approved ? 'check-circle' : 'x-circle';
        $this->level = $approved ? 'success' : 'danger';
    }

    public function title(): string
    {
        return 'Komponen poin '.mb_strtolower($this->component->status->label()).': '.$this->component->name;
    }

    public function body(): string
    {
        return $this->component->status === ReviewStatus::Approved
            ? 'Sudah bisa diberikan ke peserta kelas Anda ('.$this->component->pointsLabel().' poin).'
            : 'Alasan: '.($this->component->review_note ?: '–');
    }

    public function url(): ?string
    {
        return route('teach.points');
    }
}
