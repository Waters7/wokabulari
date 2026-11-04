<?php

namespace App\Notifications;

use App\Models\MealSchedule;

/**
 * Waktu ambil & tanda terima konsumsi diperpanjang (mis. makanan terlambat).
 */
class MealWindowExtendedNotification extends WokabulariNotification
{
    public function __construct(public MealSchedule $schedule)
    {
        $this->icon = $schedule->meal_type->icon();
        $this->level = 'info';
    }

    public function title(): string
    {
        return $this->schedule->meal_type->label().': waktu ambil diperpanjang';
    }

    public function body(): string
    {
        return 'Bisa diambil dan ditandatangani sampai '.jam($this->schedule->ends_at).' WITA'
            .($this->schedule->late_note ? ' ('.$this->schedule->late_note.')' : '').'.';
    }

    public function url(): ?string
    {
        return route('meals.index');
    }
}
