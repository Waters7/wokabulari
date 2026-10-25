<?php

namespace App\Actions\Health;

use App\Enums\DormAttendanceStatus;
use App\Enums\HealthReportStatus;
use App\Enums\HealthSeverity;
use App\Enums\LeaveType;
use App\Enums\ReviewStatus;
use App\Enums\StaffUnit;
use App\Models\DormAttendance;
use App\Models\HealthReport;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\EmergencyAlertNotification;
use App\Notifications\HealthReportedNotification;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * Laporan sakit di asrama (SPEC 3.18): oleh peserta sendiri, teman sekamar, atau petugas atas namanya.
 * Integrasi: izin sakit kelas (menunggu konfirmasi petugas), absen asrama "sakit di kamar", notifikasi.
 */
class ReportSickness
{
    public function __construct(
        protected ImageService $images,
        protected HandleHealthReport $handle,
    ) {}

    /**
     * @param  array{symptoms?: list<string>, description?: ?string, started_at?: ?string, temperature?: float|string|null, severity: string, needs_special_meal?: bool, special_meal_note?: ?string, deliver_to_room?: bool}  $data
     */
    public function handle(User $participant, User $reporter, array $data, ?UploadedFile $photo = null): HealthReport
    {
        $this->authorizeReporter($participant, $reporter);

        $severity = HealthSeverity::from($data['severity']);
        $room = $participant->currentRoomAssignment?->room;

        $report = DB::transaction(function () use ($participant, $reporter, $data, $photo, $severity, $room) {
            $report = HealthReport::query()->create([
                'user_id' => $participant->id,
                'reported_by' => $reporter->id,
                'dormitory_id' => $room?->dormitory_id,
                'dorm_room_id' => $room?->id,
                'symptoms' => array_values(array_intersect($data['symptoms'] ?? [], array_keys(HealthReport::SYMPTOMS))),
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'started_at' => filled($data['started_at'] ?? null) ? $data['started_at'] : null,
                'temperature' => filled($data['temperature'] ?? null) ? (float) $data['temperature'] : null,
                'severity' => $severity,
                'photo_path' => $photo ? $this->images->storeUpload($photo, 'health-photos') : null,
                'is_emergency' => $severity === HealthSeverity::Emergency,
                'needs_special_meal' => (bool) ($data['needs_special_meal'] ?? false),
                'status' => HealthReportStatus::Reported,
                'reported_at' => now(),
            ]);

            $this->integrate($report);

            if ($report->needs_special_meal) {
                $this->handle->requestSpecialMeal($report, $reporter, $data['special_meal_note'] ?? 'Makanan lunak/khusus untuk peserta sakit', (bool) ($data['deliver_to_room'] ?? true));
            }

            return $report;
        });

        $this->notify($report);

        return $report;
    }

    /**
     * Tombol DARURAT: setiap penekanan dicatat; notifikasi prioritas dibatasi agar tidak membanjiri petugas.
     */
    public function emergency(User $participant, User $reporter, ?float $latitude = null, ?float $longitude = null): HealthReport
    {
        $this->authorizeReporter($participant, $reporter);

        // Gabungkan dengan laporan darurat yang masih terbuka dalam 30 menit terakhir.
        $report = HealthReport::query()->where('user_id', $participant->id)->where('is_emergency', true)->open()
            ->where('reported_at', '>=', now()->subMinutes(30))->latest('reported_at')->first();

        if (! $report) {
            $room = $participant->currentRoomAssignment?->room;
            $report = HealthReport::query()->create([
                'user_id' => $participant->id,
                'reported_by' => $reporter->id,
                'dormitory_id' => $room?->dormitory_id,
                'dorm_room_id' => $room?->id,
                'symptoms' => [],
                'description' => 'Tombol darurat ditekan.',
                'severity' => HealthSeverity::Emergency,
                'is_emergency' => true,
                'status' => HealthReportStatus::Reported,
                'reported_at' => now(),
            ]);
            $this->integrate($report);
        }

        activity('kesehatan')->causedBy($reporter)->performedOn($report)
            ->withProperties(['latitude' => $latitude, 'longitude' => $longitude])
            ->log('Menekan tombol darurat');

        $key = 'emergency:'.$participant->id;
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 120);
            Notification::send($this->onDutyStaff(), new EmergencyAlertNotification($report->load(['user.profile', 'dormitory', 'room'])));
            Notification::send($participant->guardians()->get(), new HealthReportedNotification($report, 'guardian'));
        }

        return $report;
    }

    /** Izin sakit kelas (menunggu konfirmasi) + absen asrama malam ini "sakit di kamar". */
    protected function integrate(HealthReport $report): void
    {
        $exists = LeaveRequest::query()->where('user_id', $report->user_id)->where('type', LeaveType::Sick)
            ->whereDate('date_from', '<=', today())->whereDate('date_to', '>=', today())
            ->whereIn('status', [ReviewStatus::Pending, ReviewStatus::Approved])->exists();

        if (! $exists) {
            LeaveRequest::query()->create([
                'user_id' => $report->user_id,
                'date_from' => today(),
                'date_to' => today(),
                'type' => LeaveType::Sick,
                'reason' => 'Laporan sakit di asrama'.($report->symptoms ? ': '.implode(', ', $report->symptomLabels()) : ''),
                'status' => ReviewStatus::Pending,
                'health_report_id' => $report->id,
            ]);
        }

        $attendance = DormAttendance::query()->where('user_id', $report->user_id)->whereDate('date', today())->first()
            ?? new DormAttendance(['user_id' => $report->user_id, 'date' => today()->toDateString(), 'attempts' => 0]);
        if (! $attendance->exists || $attendance->status !== DormAttendanceStatus::Confirmed) {
            $attendance->fill(['dormitory_id' => $report->dormitory_id, 'status' => DormAttendanceStatus::SickInRoom, 'note' => 'Otomatis dari laporan sakit'])->save();
        }
    }

    protected function notify(HealthReport $report): void
    {
        $report->load(['user', 'dormitory', 'room']);
        $staff = $report->is_emergency
            ? $this->onDutyStaff()
            : User::query()->inUnits(StaffUnit::Dormitory, StaffUnit::Health)->get();

        Notification::send($staff, $report->is_emergency ? new EmergencyAlertNotification($report) : new HealthReportedNotification($report));
        Notification::send($report->user->guardians()->get(), new HealthReportedNotification($report, 'guardian'));
    }

    /** Semua petugas yang bertugas untuk keadaan darurat: unit asrama & kesehatan. */
    protected function onDutyStaff()
    {
        return User::query()->inUnits(StaffUnit::Dormitory, StaffUnit::Health)->get();
    }

    protected function authorizeReporter(User $participant, User $reporter): void
    {
        if (! $participant->isParticipant()) {
            throw new InvalidArgumentException('Laporan sakit hanya untuk peserta.');
        }

        if ($reporter->is($participant) || $reporter->can('health.handle')) {
            return;
        }

        // Teman sekamar boleh melaporkan atas nama peserta.
        $room = $participant->currentRoomAssignment?->dorm_room_id;
        if ($room && $reporter->isParticipant() && $reporter->currentRoomAssignment?->dorm_room_id === $room) {
            return;
        }

        throw new InvalidArgumentException('Ngana hanya bisa melapor untuk diri sendiri atau teman sekamar.');
    }
}
