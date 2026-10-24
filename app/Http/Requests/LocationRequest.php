<?php

namespace App\Http\Requests;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('location.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('location'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Location $location = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(LocationType::class)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m' => ['required', 'integer', 'between:10,5000'],
        ];
    }
}
