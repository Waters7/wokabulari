<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Events\DailyAttendanceRecorded;
use App\Models\DailyAttendance;
use App\Models\User;
use App\Services\FaceMatcher;
use App\Services\ImageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Absen harian peserta: verifikasi wajah + geofence di server (SPEC 3.2).
 *
 * Hasil gagal verifikasi tetap disimpan sebagai "ditolak" beserta alasannya; peserta boleh mencoba lagi.
 */
class RecordDailyAttendance
{
    public function __construct(
        protected FaceMatcher $faces,
        protected ImageService $images,
    ) {}

    /**
     * @param  array{descriptor?: list<float>|null, selfie?: string|null, latitude?: float|null, longitude?: float|null, accuracy?: float|null, device?: string|null}  $payload
     */
    public function handle(User $user, array $payload, ?Carbon $now = null): DailyAttendance
    {
        $now ??= now();

        if (! $user->isParticipant()) {
            throw new InvalidArgumentException('Absen harian hanya untuk peserta.');
        }

        $existing = DailyAttendance::query()->where('user_id', $user->id)->whereDate('date', $now->toDateString())->first();

        if ($existing && $existing->status !== AttendanceStatus::Rejected) {
            throw new InvalidArgumentException('Ngana so absen hari ini ('.$existing->status->label().').');
        }

        $openFrom = $now->copy()->setTimeFromTimeString((string) setting('attendance.open_from'));
        if ($now->lt($openFrom)) {
            throw new InvalidArgumentException('Absen harian dibuka mulai pukul '.jam($openFrom).' WITA.');
        }

        $classroom = $user->activeClassroom();
        if (! $classroom) {
            throw new InvalidArgumentException('Ngana belum terdaftar di kelas aktif. Hubungi admin program.');
        }

        $profile = $user->profile;
        $faceEnabled = $this->faces->enabled();

        if ($faceEnabled && ! $profile?->hasFaceReference()) {
            throw new InvalidArgumentException('Daftarkan wajah referensi dulu di menu Profil → Wajah referensi.');
        }

        $latitude = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $longitude = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $accuracy = isset($payload['accuracy']) ? (float) $payload['accuracy'] : null;

        $rejection = null;
        $distance = null;
        $faceDistance = null;

        // 1. Lokasi
        if (config('wokabulari.geofence_enabled')) {
            $location = $classroom->location;

            if (! $location) {
                throw new InvalidArgumentException('Lokasi kelas belum diatur. Hubungi admin program.');
            }

            if ($latitude === null || $longitude === null) {
                $rejection = 'Lokasi GPS tidak terkirim.';
            } elseif ($accuracy === null || $accuracy > (float) setting('attendance.max_gps_accuracy_m')) {
                $rejection = 'Akurasi GPS terlalu rendah ('.angka($accuracy).' m). Coba di tempat terbuka.';
            } else {
                $distance = round($location->distanceTo($latitude, $longitude), 2);
                if ($distance > $location->radius_m) {
                    $rejection = 'Di luar radius lokasi kelas ('.angka($distance).' m dari '.$location->name.').';
                }
            }
        } elseif ($latitude !== null && $longitude !== null && $classroom->location) {
            $distance = round($classroom->location->distanceTo($latitude, $longitude), 2);
        }

        // 2. Wajah
        if ($faceEnabled && $rejection === null) {
            try {
                $match = $this->faces->match($profile, $payload['descriptor'] ?? []);
                $faceDistance = $match['distance'];
                if (! $match['matched']) {
                    $rejection = 'Wajah tidak cocok dengan referensi. Coba lagi dengan pencahayaan yang baik.';
                }
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

        $selfiePath = null;
        if (! empty($payload['selfie'])) {
            $selfiePath = $this->images->storeDataUri($payload['selfie'], 'selfies/'.$now->format('Y/m'), 480, 70);
        }

        $lateAfter = $now->copy()->setTimeFromTimeString((string) setting('attendance.late_after'));
        $status = $rejection ? AttendanceStatus::Rejected : ($now->gt($lateAfter) ? AttendanceStatus::Late : AttendanceStatus::Present);

        $attendance = DB::transaction(function () use ($user, $now, $existing, $selfiePath, $faceDistance, $latitude, $longitude, $accuracy, $distance, $status, $rejection, $payload) {
            $attendance = $existing ?? new DailyAttendance(['user_id' => $user->id, 'date' => $now->toDateString(), 'attempts' => 0]);

            $attendance->fill([
                'checked_in_at' => $now,
                'selfie_path' => $selfiePath ?? $attendance->selfie_path,
                'face_distance' => $faceDistance,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy_m' => $accuracy,
                'distance_m' => $distance,
                'status' => $status,
                'reject_reason' => $rejection,
                'device_info' => isset($payload['device']) ? mb_substr((string) $payload['device'], 0, 250) : null,
            ]);
            $attendance->attempts = ($attendance->attempts ?? 0) + 1;
            $attendance->save();

            return $attendance;
        });

        if ($attendance->isAttended()) {
            event(new DailyAttendanceRecorded($attendance));
        }

        return $attendance;
    }
}
