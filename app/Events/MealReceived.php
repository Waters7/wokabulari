<?php

namespace App\Events;

use App\Models\MealReceipt;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MealReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public MealReceipt $receipt) {}
}
