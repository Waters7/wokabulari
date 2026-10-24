<?php

namespace App\Actions\Sessions;

use App\Enums\AttendanceStatus;
use App\Events\SessionAttendanceRecorded;
use App\Models\DailyAttendance;
use App\Models\SessionAttendance;
use App\Models\SessionAttendanceCode;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Peserta memasukkan kode sesi (SPEC 3.4). Validasi sepenuhnya di server; rate limit 5×/menit.
 */
class SubmitSessionCode
{
    public const MAX_ATTEMPTS = 5;

    /**
     * @param  array{latitude?: float|null, longitude?: float|null}  $location
     */
    public function handle(User $user, string $code, array $location = []): SessionAttendance
    {
        $key = 'session-code:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new InvalidArgumentException('Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');
        }

        try {
            return $this->attempt($user, $code, $location);
        } catch (InvalidArgumentException $e) {
            RateLimiter::hit($key, 60);
            activity('kode-sesi')->causedBy($user)->withProperties(['kode' => Str::upper(trim($code)), 'alasan' => $e->getMessage()])->log('Percobaan kode sesi gagal');

            throw $e;
        }
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null}  $location
     */
    protected function attempt(User $user, string $code, array $location): SessionAttendance
    {
        $code = Str::upper(preg_replace('/\s+/', '', $code));

        if (! preg_match('/^['.SessionAttendanceCode::ALPHABET.']{6}$/', $code)) {
            throw new InvalidArgumentException('Format kode tidak valid (6 karakter).');
        }

        $sessionCode = SessionAttendanceCode::query()->with('session.classroom.location')->where('code', $code)->latest('id')->first();

        if (! $sessionCode || ! $sessionCode->isUsable()) {
            throw new InvalidArgumentException('Kode tidak aktif atau sudah kedaluwarsa.');
        }

        $session = $sessionCode->session;

        if (! $session->classroom->participants()->whereKey($user->id)->exists()) {
            throw new InvalidArgumentException('Ngana tidak terdaftar di kelas sesi ini.');
        }

        $daily = DailyAttendance::query()->where('user_id', $user->id)->whereDate('date', today())->first();
        if (! $daily || ! $daily->isAttended()) {
            throw new InvalidArgumentException('Absen harian dulu sebelum input kode sesi.');
        }

        if (SessionAttendance::query()->where('learning_session_id', $session->id)->where('user_id', $user->id)->whereIn('status', ['present', 'late'])->exists()) {
            throw new InvalidArgumentException('Ngana so input kode untuk sesi ini.');
        }

        if (setting('session.validate_location') && config('wokabulari.geofence_enabled') && $session->classroom->location) {
            $lat = $location['latitude'] ?? null;
            $lng = $location['longitude'] ?? null;
            if ($lat === null || $lng === null || ! $session->classroom->location->contains((float) $lat, (float) $lng)) {
                throw new InvalidArgumentException('Ngana berada di luar lokasi kelas.');
            }
        }

        $attendance = SessionAttendance::query()->updateOrCreate(
            ['learning_session_id' => $session->id, 'user_id' => $user->id],
            [
                'attendance_code_id' => $sessionCode->id,
                'submitted_at' => now(),
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'status' => AttendanceStatus::Present,
                'marked_by' => null,
                'note' => null,
            ],
        );

        RateLimiter::clear('session-code:'.$user->id);
        event(new SessionAttendanceRecorded($attendance));

        return $attendance;
    }
}
