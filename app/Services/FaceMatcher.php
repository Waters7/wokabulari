<?php

namespace App\Services;

use App\Models\ParticipantProfile;
use InvalidArgumentException;

/**
 * Pencocokan wajah di server. Descriptor referensi tidak pernah dikirim ke browser.
 */
class FaceMatcher
{
    public const DIMENSIONS = 128;

    /** Batas jarak antar-sampel saat pendaftaran agar tidak tercampur wajah lain. */
    public const ENROLL_CONSISTENCY = 0.6;

    public function enabled(): bool
    {
        return (bool) config('wokabulari.face_verification_enabled');
    }

    public function threshold(): float
    {
        return (float) setting('attendance.face_threshold', 0.5);
    }

    /**
     * Jarak Euclidean dua descriptor.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function distance(array $a, array $b): float
    {
        $this->assertDescriptor($a);
        $this->assertDescriptor($b);

        $sum = 0.0;
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $diff = (float) $a[$i] - (float) $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Cocokkan descriptor terhadap referensi peserta.
     *
     * @param  list<float>  $descriptor
     * @return array{matched: bool, distance: float}
     */
    public function match(ParticipantProfile $profile, array $descriptor): array
    {
        if (! $profile->hasFaceReference()) {
            throw new InvalidArgumentException('Wajah referensi belum didaftarkan.');
        }

        $distance = $this->distance($profile->face_descriptor, $descriptor);

        return ['matched' => $distance <= $this->threshold(), 'distance' => round($distance, 4)];
    }

    /**
     * Rata-ratakan beberapa sampel menjadi satu descriptor referensi.
     *
     * @param  list<list<float>>  $samples
     * @return list<float>
     */
    public function average(array $samples): array
    {
        if (count($samples) < 3) {
            throw new InvalidArgumentException('Minimal 3 sampel wajah dibutuhkan.');
        }

        foreach ($samples as $sample) {
            $this->assertDescriptor($sample);
        }

        // Semua sampel harus mirip satu sama lain (wajah orang yang sama).
        for ($i = 0; $i < count($samples); $i++) {
            for ($j = $i + 1; $j < count($samples); $j++) {
                if ($this->distance($samples[$i], $samples[$j]) > self::ENROLL_CONSISTENCY) {
                    throw new InvalidArgumentException('Sampel wajah tidak konsisten. Ulangi di tempat terang dan pastikan hanya satu wajah.');
                }
            }
        }

        $average = array_fill(0, self::DIMENSIONS, 0.0);
        foreach ($samples as $sample) {
            for ($i = 0; $i < self::DIMENSIONS; $i++) {
                $average[$i] += (float) $sample[$i] / count($samples);
            }
        }

        return array_map(fn (float $v) => round($v, 6), $average);
    }

    /**
     * @param  mixed  $descriptor
     */
    public function assertDescriptor($descriptor): void
    {
        if (! is_array($descriptor) || count($descriptor) !== self::DIMENSIONS) {
            throw new InvalidArgumentException('Data wajah tidak valid.');
        }

        foreach ($descriptor as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > 1) {
                throw new InvalidArgumentException('Data wajah tidak valid.');
            }
        }
    }
}
