<?php

namespace App\Http\Requests;

use App\Enums\DormRequestCategory;
use App\Enums\ItemCondition;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DormRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isParticipant();
    }

    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * Keluhan/permintaan asrama oleh peserta.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'category' => ['required', Rule::enum(DormRequestCategory::class)],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'photo' => ['nullable', 'image', 'max:8192'],
        ];
    }

    /**
     * Barang inventaris kamar oleh petugas.
     *
     * @return array<string, mixed>
     */
    public static function inventoryRules(): array
    {
        return [
            'dorm_room_id' => ['required', Rule::exists('dorm_rooms', 'id')->whereNull('deleted_at')],
            'item_name' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
