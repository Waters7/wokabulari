<?php

namespace App\Events;

use App\Models\Assessment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Assessment $assessment) {}
}
