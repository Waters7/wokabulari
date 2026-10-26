<?php

namespace App\Actions\Meals;

use App\Enums\MealType;
use App\Models\User;
use App\Services\SettingService;

/**
 * Tim konsumsi mengatur jenis konsumsi yang diterima peserta tidak asrama.
 */
class SetNonResidentMealTypes
{
    public function __construct(protected SettingService $settings) {}

    /**
     * @param  list<string>  $types
     */
    public function handle(array $types, User $actor): void
    {
        // Simpan urut sesuai waktu makan.
        $values = collect(MealType::cases())
            ->filter(fn (MealType $type) => in_array($type->value, $types, true))
            ->map->value
            ->values()
            ->all();

        $this->settings->set('meals.non_resident_types', $values);

        activity('konsumsi')->causedBy($actor)
            ->withProperties(['jenis' => $values])
            ->log('Mengatur konsumsi peserta tidak asrama');
    }
}
