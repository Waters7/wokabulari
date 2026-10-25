<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nomor HP Indonesia; spasi, titik, dan tanda hubung boleh (0812-3456-7890, +62 812 …).
 */
class IndonesianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = (string) $value;

        if (preg_match('/[^0-9+\s().-]/', $raw) || ! preg_match('/^628\d{7,12}$/', (string) Phone::normalize($raw))) {
            $fail('No. HP tidak valid. Contoh: 081234567890.');
        }
    }
}
