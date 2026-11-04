<?php

namespace App\Actions\Meals;

use App\Models\MealSchedule;
use App\Models\User;
use App\Notifications\MealWindowExtendedNotification;
use App\Services\MealRoster;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Makanan terlambat: perpanjang waktu pengambilan & tanda terima (bisa dibuka lagi setelah ditutup,
 * paling lambat 23.59 pada hari yang sama). Penerima yang belum mengambil bisa diberi tahu.
 */
class ExtendMealWindow
{
    public function __construct(protected MealRoster $roster) {}

    /**
     * @return int jumlah penerima yang diberi tahu
     */
    public function handle(MealSchedule $schedule, User $by, string $until, ?string $note = null, bool $notify = true): int
    {
        if ($schedule->date->lt(today())) {
            throw new InvalidArgumentException('Jadwal hari yang sudah lewat tidak bisa diperpanjang.');
        }

        $ends = $schedule->date->setTimeFromTimeString($until);

        if ($ends->lte($schedule->ends_at)) {
            throw new InvalidArgumentException('Jam baru harus setelah jam selesai sekarang ('.jam($schedule->ends_at).').');
        }

        if ($schedule->date->isToday() && $ends->lte(now())) {
            throw new InvalidArgumentException('Jam baru harus setelah jam sekarang.');
        }

        $schedule->update([
            'ends_at' => $ends,
            'extended_at' => now(),
            'extended_by' => $by->id,
            'late_note' => filled($note) ? mb_substr(trim($note), 0, 200) : 'Makanan terlambat',
        ]);

        if (! $notify || ! $schedule->date->isToday() || now()->lt($schedule->starts_at->subHour())) {
            return 0;
        }

        $taken = $schedule->receipts()->pluck('user_id');
        $recipients = $this->roster->recipients($schedule->meal_type)->reject(fn ($user) => $taken->contains($user->id))->values();
        Notification::send($recipients, new MealWindowExtendedNotification($schedule));

        return $recipients->count();
    }
}
