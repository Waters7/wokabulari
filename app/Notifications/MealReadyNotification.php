<?php

namespace App\Notifications;

use App\Models\MealDelivery;
use Illuminate\Support\Str;

/**
 * Makanan siap diambil: jam dan tempat dari laporan vendor.
 */
class MealReadyNotification extends WokabulariNotification
{
    public function __construct(public MealDelivery $delivery)
    {
        $this->icon = $delivery->schedule->meal_type->icon();
        $this->level = 'success';
    }

    public function title(): string
    {
        return $this->delivery->schedule->meal_type->label().' siap diambil';
    }

    public function body(): string
    {
        $schedule = $this->delivery->schedule;

        return 'Mulai '.jam($this->delivery->ready_at).' WITA di '.$this->delivery->location
            .' (s.d. '.jam($schedule->ends_at).'). Menu: '.Str::limit(preg_replace('/\s*\R\s*/', ', ', $this->delivery->menu), 90);
    }

    public function url(): ?string
    {
        return route('meals.index');
    }
}
