<?php

namespace App\Actions\Health;

use App\Actions\Leave\ReviewLeaveRequest;
use App\Enums\HealthActionType;
use App\Enums\HealthReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\SpecialMealStatus;
use App\Enums\StaffUnit;
use App\Models\HealthReport;
use App\Models\HealthReportAction;
use App\Models\SpecialMealRequest;
use App\Models\User;
use App\Notifications\SpecialMealNeededNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Penanganan laporan sakit: timeline tindakan, konfirmasi izin sakit kelas, permintaan makanan khusus.
 */
class HandleHealthReport
{
    public function __construct(protected ReviewLeaveRequest $reviewLeave) {}

    /**
     * @param  array{action_type: string, medicine?: ?string, dosage?: ?string, referral_place?: ?string, condition_note?: ?string, next_check_at?: ?string}  $data
     */
    public function addAction(HealthReport $report, User $officer, array $data): HealthReportAction
    {
        $type = HealthActionType::from($data['action_type']);

        if ($type === HealthActionType::Referral && blank($data['referral_place'] ?? null)) {
            throw new InvalidArgumentException('Tempat rujukan wajib diisi.');
        }

        if ($type === HealthActionType::Medication && blank($data['medicine'] ?? null)) {
            throw new InvalidArgumentException('Nama obat wajib diisi.');
        }

        return DB::transaction(function () use ($report, $officer, $data, $type) {
            $action = $report->actions()->create([
                'handled_by' => $officer->id,
                'action_type' => $type,
                'medicine' => $data['medicine'] ?? null,
                'dosage' => $data['dosage'] ?? null,
                'referral_place' => $data['referral_place'] ?? null,
                'condition_note' => filled($data['condition_note'] ?? null) ? trim($data['condition_note']) : null,
                'next_check_at' => filled($data['next_check_at'] ?? null) ? $data['next_check_at'] : null,
                'acted_at' => now(),
            ]);

            $status = $type->resultingStatus();
            $report->update([
                'status' => $status,
                'resolved_at' => $status === HealthReportStatus::Recovered ? now() : null,
            ]);

            // Pemantauan terjadwal sebelumnya dianggap selesai bila ada tindakan baru.
            $report->actions()->whereKeyNot($action->id)->whereNotNull('next_check_at')->whereNull('reminded_at')->update(['reminded_at' => now()]);

            return $action;
        });
    }

    /** Petugas mengonfirmasi izin sakit kelas yang dibuat otomatis dari laporan. */
    public function confirmSickLeave(HealthReport $report, User $officer, bool $approve = true, ?string $note = null): void
    {
        $leave = $report->sickLeave;

        if (! $leave || $leave->status !== ReviewStatus::Pending) {
            throw new InvalidArgumentException('Tidak ada izin sakit kelas yang menunggu konfirmasi.');
        }

        $this->reviewLeave->handle($leave, $officer, $approve, $note ?? ($approve ? 'Dikonfirmasi petugas kesehatan/asrama' : null));
    }

    public function requestSpecialMeal(HealthReport $report, User $by, string $request, bool $deliverToRoom = true, ?string $mealType = null): SpecialMealRequest
    {
        $meal = SpecialMealRequest::query()->create([
            'user_id' => $report->user_id,
            'health_report_id' => $report->id,
            'date' => today(),
            'meal_type' => $mealType,
            'request' => trim($request),
            'delivered_to_room' => $deliverToRoom,
            'status' => SpecialMealStatus::Pending,
        ]);

        Notification::send(User::query()->inUnits(StaffUnit::Consumption)->get(), new SpecialMealNeededNotification($meal->load('user.currentRoomAssignment.room')));

        return $meal;
    }
}
