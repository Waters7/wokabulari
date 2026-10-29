<?php

namespace App\Http\Requests;

use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meal.deliver') && $this->user()->isVendor();
    }

    public function rules(): array
    {
        return self::rulesFor(! $this->filled('meal_schedule_id'), true);
    }

    /**
     * Laporan pengantaran vendor. Jenis & tanggal hanya diisi bila belum ada jadwal dari tim konsumsi.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(bool $withoutSchedule, bool $photosRequired): array
    {
        $photo = [$photosRequired ? 'required' : 'nullable', 'image', 'max:10240'];

        return [
            'meal_schedule_id' => ['nullable', 'integer', 'exists:meal_schedules,id'],
            'date' => [$withoutSchedule ? 'required' : 'nullable', 'date', 'after_or_equal:today', 'before_or_equal:+7 days'],
            'meal_type' => [$withoutSchedule ? 'required' : 'nullable', Rule::enum(MealType::class)],
            'ready_time' => ['required', 'date_format:H:i'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'unit' => ['required', Rule::enum(DeliveryUnit::class)],
            'menu' => ['required', 'string', 'max:1000'],
            'location' => ['required', 'string', 'max:150'],
            'menu_photo' => $photo,
            'total_photo' => $photo,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'date' => 'tanggal terima', 'meal_type' => 'waktu makan', 'ready_time' => 'jam siap diambil',
            'quantity' => 'jumlah', 'unit' => 'satuan', 'menu' => 'menu', 'location' => 'tempat makanan tersedia',
            'menu_photo' => 'foto menu', 'total_photo' => 'foto total makanan',
        ];
    }
}
