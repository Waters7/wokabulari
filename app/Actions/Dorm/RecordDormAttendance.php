<?php

namespace App\Actions\Dorm;

use App\Enums\DormAttendanceStatus;
use App\Models\DormAttendance;
use App\Models\DormExitPermit;
use App\Models\User;
use App\Services\FaceMatcher;
use App\Services\ImageService;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Absen asrama malam: selfie + verifikasi wajah + geofence asrama, dalam jendela waktu (SPEC 3.15).
 * Setelah jendela berakhir masih bisa sampai batas terlambat (pengaturan dorm.late_until) dan ditandai terlambat.
 * Hasil valid berstatus "menunggu konfirmasi" sampai pengelola asrama mengonfirmasi, atau langsung
 * terkonfirmasi bila pengaturan sistem "Konfirmasi otomatis absen asrama" aktif.
 */
class RecordDormAttendance
{
    public function __construct(
        protected FaceMatcher $faces,
        protected ImageService $images,
        protected ConfirmDormAttendance $confirm,
    ) {}

    /**
     * @param  array{descriptor?: list<float>|null, selfie?: string|null, latitude?: float|null, longitude?: float|null, accuracy?: float|null}  $payload
     */
    public function handle(User $user, array $payload, ?CarbonInterface $now = null): DormAttendance
    {
        $now ??= now();

        if (! $user->isParticipant()) {
            throw new InvalidArgumentException('Absen asrama hanya untuk peserta.');
        }

        $dormitory = $user->currentDormitory();
        if (! $dormitory) {
            throw new InvalidArgumentException('Ngana belum ditempatkan di kamar asrama. Hubungi pengelola asrama.');
        }

        $existing = DormAttendance::query()->where('user_id', $user->id)->whereDate('date', $now->toDateString())->first();
        if ($existing && $existing->status !== DormAttendanceStatus::Rejected) {
            throw new InvalidArgumentException('Absen asrama malam ini so tercatat ('.$existing->status->label().').');
        }

        if (DormExitPermit::query()->where('user_id', $user->id)->covering($now)->whereIn('status', ['out', 'late_return'])->exists()) {
            throw new InvalidArgumentException('Ngana tercatat sedang izin keluar. Minta petugas memindai QR saat kembali.');
        }

        [$opens, $closes] = $dormitory->checkWindow($now);
        $lateUntil = $dormitory->lateUntil($now);
        if ($now->lt($opens) || $now->gt($lateUntil)) {
            throw new InvalidArgumentException('Absen asrama hanya pukul '.jam($opens).'–'.jam($closes).' WITA (terlambat s.d. '.jam($lateUntil).'). Di luar itu, lapor ke pengelola asrama.');
        }

        $profile = $user->profile;
        $faceEnabled = $this->faces->enabled();
        if ($faceEnabled && ! $profile?->hasFaceReference()) {
            throw new InvalidArgumentException('Daftarkan wajah referensi dulu di menu Profil → Wajah referensi.');
        }

        $latitude = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $longitude = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $accuracy = isset($payload['accuracy']) ? (float) $payload['accuracy'] : null;
        $location = $dormitory->location;
        $rejection = null;
        $distance = null;
        $faceDistance = null;

        if (config('wokabulari.geofence_enabled')) {
            if (! $location) {
                throw new InvalidArgumentException('Lokasi asrama belum diatur. Hubungi admin program.');
            }

            if ($latitude === null || $longitude === null) {
                $rejection = 'Lokasi GPS tidak terkirim.';
            } elseif ($accuracy === null || $accuracy > (float) setting('attendance.max_gps_accuracy_m')) {
                $rejection = 'Akurasi GPS terlalu rendah ('.angka($accuracy).' m).';
            } else {
                $distance = round($location->distanceTo($latitude, $longitude), 2);
                if ($distance > $location->radius_m) {
                    $rejection = 'Di luar radius asrama ('.angka($distance).' m dari '.$location->name.').';
                }
            }
        } elseif ($latitude !== null && $longitude !== null && $location) {
            $distance = round($location->distanceTo($latitude, $longitude), 2);
        }

        if ($faceEnabled && $rejection === null) {
            try {
                $match = $this->faces->match($profile, $payload['descriptor'] ?? []);
                $faceDistance = $match['distance'];
                $rejection = $match['matched'] ? null : 'Wajah tidak cocok dengan referensi.';
            } catch (InvalidArgumentException) {
                $rejection = 'Data wajah tidak terbaca. Ulangi pengambilan wajah.';
            }
        } elseif (! empty($payload['descriptor']) && $profile?->hasFaceReference()) {
            try {
                $faceDistance = $this->faces->match($profile, $payload['descriptor'])['distance'];
            } catch (Throwable) {
                $faceDistance = null;
            }
        }

        $selfiePath = ! empty($payload['selfie'])
            ? $this->images->storeDataUri($payload['selfie'], 'dorm-selfies/'.$now->format('Y/m'), 480, 70)
            : null;

        $attendance = $existing ?? new DormAttendance(['user_id' => $user->id, 'date' => $now->toDateString(), 'attempts' => 0]);
        $attendance->fill([
            'dormitory_id' => $dormitory->id,
            'checked_in_at' => $now,
            'selfie_path' => $selfiePath ?? $attendance->selfie_path,
            'face_distance' => $faceDistance,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_m' => $accuracy,
            'distance_m' => $distance,
            'status' => $rejection ? DormAttendanceStatus::Rejected : DormAttendanceStatus::PendingConfirmation,
            'is_late' => $now->gt($closes),
            'reject_reason' => $rejection,
        ]);
        $attendance->attempts = ($attendance->attempts ?? 0) + 1;
        $attendance->save();

        if ($rejection === null && ConfirmDormAttendance::autoEnabled()) {
            $this->confirm->auto($attendance);
        }

        return $attendance;
    }
}
