<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Isi & ubah massal jadwal makan (rentang tanggal, hari, jenis makan, perubahan).
 */
class BulkMealScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('meal.manage');
    }

    public function rules(): array
    {
        return self::rangeRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rangeRules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ];
    }

    /**
     * Aturan isi massal untuk jenis makan yang dicentang.
     *
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    public static function fillRulesFor(array $types): array
    {
        $rules = self::rangeRules() + [
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::enum(MealType::class)],
            'existing' => ['required', Rule::in(['skip', 'update'])],
        ];

        foreach ($types as $type) {
            $rules += [
                "meals.{$type}.vendor_id" => ['required', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
                "meals.{$type}.start_time" => ['required', 'date_format:H:i'],
                "meals.{$type}.end_time" => ['required', 'date_format:H:i', "after:meals.{$type}.start_time"],
                "meals.{$type}.portions_mode" => ['required', Rule::in(['auto', 'fixed'])],
                "meals.{$type}.portions" => ["required_if:meals.{$type}.portions_mode,fixed", 'nullable', 'integer', 'min:0', 'max:5000'],
                "meals.{$type}.menu" => ['nullable', 'string', 'max:5000'],
            ];
        }

        return $rules;
    }

    /**
     * Aturan ubah massal; hanya perubahan yang dicentang yang divalidasi.
     *
     * @param  list<string>  $changes  vendor|time|portions|menu
     * @return array<string, mixed>
     */
    public static function changeRulesFor(array $changes, string $timeMode = 'set', string $portionsMode = 'auto'): array
    {
        $rules = self::filterRules() + [
            'changes' => ['required', 'array', 'min:1'],
            'changes.*' => [Rule::in(['vendor', 'time', 'portions', 'menu'])],
        ];

        if (in_array('vendor', $changes, true)) {
            $rules['vendor_id'] = ['required', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')];
        }

        if (in_array('time', $changes, true)) {
            $rules['time_mode'] = ['required', Rule::in(['set', 'shift'])];
            $rules += $timeMode === 'shift'
                ? ['shift_minutes' => ['required', 'integer', 'between:-240,240', 'not_in:0']]
                : ['start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time']];
        }

        if (in_array('portions', $changes, true)) {
            $rules['portions_mode'] = ['required', Rule::in(['auto', 'fixed'])];
            if ($portionsMode === 'fixed') {
                $rules['portions'] = ['required', 'integer', 'min:0', 'max:5000'];
            }
        }

        if (in_array('menu', $changes, true)) {
            $rules['menu'] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * Filter jadwal untuk ubah/hapus massal.
     *
     * @return array<string, mixed>
     */
    public static function filterRules(): array
    {
        return self::rangeRules() + [
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::enum(MealType::class)],
            'vendor_filter' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributesFor(): array
    {
        return [
            'from' => 'tanggal mulai', 'to' => 'tanggal akhir', 'weekdays' => 'hari', 'types' => 'waktu makan', 'changes' => 'perubahan',
            'vendor_id' => 'vendor', 'start_time' => 'jam mulai', 'end_time' => 'jam selesai', 'shift_minutes' => 'geser (menit)',
            'portions' => 'porsi', 'menu' => 'menu', 'meals.*.vendor_id' => 'vendor', 'meals.*.start_time' => 'jam mulai',
            'meals.*.end_time' => 'jam selesai', 'meals.*.portions' => 'porsi', 'meals.*.menu' => 'menu',
        ];
    }
}
