<?php

namespace Database\Factories;

use App\Enums\DeliveryUnit;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealDelivery>
 */
class MealDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meal_schedule_id' => MealSchedule::factory(),
            'vendor_id' => fn (array $attributes) => MealSchedule::query()->find($attributes['meal_schedule_id'])?->vendor_id,
            'quantity' => 60,
            'unit' => DeliveryUnit::Box,
            'menu' => "Nasi putih\nIkan woku\nTumis kangkung",
            'ready_at' => now()->subMinutes(5),
            'location' => 'Ruang makan asrama',
            'menu_photo_path' => null,
            'total_photo_path' => null,
            'notified_at' => null,
        ];
    }

    /** Siap beberapa menit lagi (belum waktunya diberi tahu). */
    public function upcoming(int $minutes = 30): static
    {
        return $this->state(fn () => ['ready_at' => now()->addMinutes($minutes)]);
    }
}
