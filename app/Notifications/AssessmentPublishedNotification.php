<?php

namespace App\Notifications;

use App\Models\Assessment;

class AssessmentPublishedNotification extends WokabulariNotification
{
    protected string $icon = 'chart-bar';

    protected string $level = 'success';

    public function __construct(public Assessment $assessment) {}

    public function title(): string
    {
        return 'Penilaian '.strtolower($this->assessment->period_type->label()).' terbit';
    }

    public function body(): string
    {
        return $this->assessment->periodLabel().': rata-rata '.angka($this->assessment->average, 1).' (predikat '.$this->assessment->grade.').';
    }

    public function url(): ?string
    {
        return route('grades.index');
    }
}
