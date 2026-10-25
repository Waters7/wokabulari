<?php

namespace App\Events;

use App\Models\TeacherRating;
use App\Models\VendorRating;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public VendorRating|TeacherRating $rating) {}
}
