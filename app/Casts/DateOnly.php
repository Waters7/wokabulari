<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Kolom DATE disimpan sebagai "Y-m-d" (tanpa jam) di semua database, dibaca sebagai CarbonImmutable awal hari.
 * Menjaga perilaku SQLite (test) sama dengan MySQL untuk pencarian & unique constraint per tanggal.
 *
 * @implements CastsAttributes<CarbonImmutable|null, mixed>
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof DateTimeInterface ? $value : CarbonImmutable::parse($value))->format('Y-m-d');
    }
}
