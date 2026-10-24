<?php

namespace App\Http\Requests;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendor.manage');
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('vendor'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(?Vendor $vendor = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
