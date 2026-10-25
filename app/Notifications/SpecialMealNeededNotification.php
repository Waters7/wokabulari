<?php

namespace App\Notifications;

use App\Models\SpecialMealRequest;

/**
 * Ke unit konsumsi: peserta sakit butuh makanan khusus/diantar ke kamar (tanpa detail medis).
 */
class SpecialMealNeededNotification extends WokabulariNotification
{
    protected string $icon = 'cake';

    protected string $level = 'warning';

    public function __construct(public SpecialMealRequest $request) {}

    public function title(): string
    {
        return 'Makanan khusus: '.$this->request->user->name;
    }

    public function body(): string
    {
        $room = $this->request->user->currentRoomAssignment?->room;

        return $this->request->request.($this->request->delivered_to_room && $room ? " · antar ke kamar {$room->number}" : '');
    }

    public function url(): ?string
    {
        return route('meals.monitor');
    }
}
