<?php

namespace App\Events;

use App\Models\AssignmentSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public AssignmentSubmission $submission) {}
}
