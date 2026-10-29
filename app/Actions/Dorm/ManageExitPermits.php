<?php

namespace App\Actions\Dorm;

use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Events\ExitPermitReturnedLate;
use App\Models\DormExitPermit;
use App\Models\LearningSession;
use App\Models\User;
use App\Notifications\ExitPermitNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Alur izin keluar asrama (SPEC 3.16):
 * diajukan → (persetujuan wali untuk jenis tertentu) → keputusan pengelola → QR keluar/masuk → kembali / terlambat.
 */
class ManageExitPermits
{
    /**
     * @param  array{type: string, reason: string, destination: string, companion?: ?string, contact_phone: string, planned_departure: string, planned_return: string}  $data
     */
    public function submit(User $participant, array $data, ?UploadedFile $attachment = null): DormExitPermit
    {
        if (! $participant->isParticipant() || ! $participant->currentDormitory()) {
            throw new InvalidArgumentException('Izin keluar hanya untuk peserta yang tinggal di asrama.');
        }

        $type = ExitPermitType::from($data['type']);
        $departure = CarbonImmutable::parse($data['planned_departure']);
        $return = CarbonImmutable::parse($data['planned_return']);

        if ($return->lte($departure)) {
            throw new InvalidArgumentException('Rencana kembali harus setelah rencana keluar.');
        }

        if ($type !== ExitPermitType::Emergency) {
            $minHours = (int) setting('exit.min_hours_before', 3);
            if ($departure->lt(now()->addHours($minHours))) {
                throw new InvalidArgumentException("Pengajuan paling lambat {$minHours} jam sebelum keluar. Untuk keadaan mendesak pilih jenis Darurat.");
            }

            $weekly = DormExitPermit::query()->where('user_id', $participant->id)
                ->whereBetween('planned_departure', [$departure->startOfWeek(), $departure->endOfWeek()])
                ->whereNotIn('status', [ExitPermitStatus::Rejected, ExitPermitStatus::Cancelled])->count();
            $max = (int) setting('exit.max_per_week', 2);
            if ($weekly >= $max) {
                throw new InvalidArgumentException("Batas izin keluar {$max}× per minggu sudah tercapai.");
            }

            if ($session = $this->conflictingSession($participant, $departure, $return)) {
                throw new InvalidArgumentException('Bertabrakan dengan sesi '.$session->topic.' ('.tanggal($session->date, 'D, j M').' '.$session->start_time.'). Pilih waktu lain atau jenis Darurat.');
            }
        } elseif ($departure->lt(now()->subHour())) {
            throw new InvalidArgumentException('Waktu keluar tidak boleh di masa lalu.');
        }

        // Jenis tertentu butuh persetujuan wali bila wali sudah terhubung & terverifikasi.
        $guardians = $participant->guardians()->get();
        $guardianRequired = in_array($type->value, (array) setting('exit.guardian_required_types', []), true) && $guardians->isNotEmpty();

        $permit = DormExitPermit::query()->create([
            'user_id' => $participant->id,
            'type' => $type,
            'reason' => trim($data['reason']),
            'destination' => trim($data['destination']),
            'companion' => filled($data['companion'] ?? null) ? trim($data['companion']) : null,
            'contact_phone' => trim($data['contact_phone']),
            'planned_departure' => $departure,
            'planned_return' => $return,
            'attachment_path' => $attachment?->storeAs('exit-attachments', Str::uuid().'.'.$attachment->guessExtension(), 'private'),
            'status' => ExitPermitStatus::Submitted,
            'guardian_required' => $guardianRequired,
        ]);

        $guardianRequired
            ? Notification::send($guardians, new ExitPermitNotification($permit, 'guardian_needed'))
            : Notification::send($this->approvers(), new ExitPermitNotification($permit, 'review_needed'));

        return $permit;
    }

    public function guardianDecide(DormExitPermit $permit, User $guardian, bool $approve, ?string $note = null): DormExitPermit
    {
        if (! $guardian->isGuardianOf($permit->user)) {
            throw new InvalidArgumentException('Ngana bukan wali terverifikasi peserta ini.');
        }

        if (! $permit->guardian_required || $permit->status !== ExitPermitStatus::Submitted) {
            throw new InvalidArgumentException('Izin ini tidak sedang menunggu persetujuan wali.');
        }

        if (! $approve && blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $permit->update([
            'status' => $approve ? ExitPermitStatus::GuardianApproved : ExitPermitStatus::Rejected,
            'guardian_id' => $guardian->id,
            'guardian_decided_at' => now(),
            'guardian_note' => filled($note) ? trim($note) : null,
        ]);

        if ($approve) {
            Notification::send($this->approvers(), new ExitPermitNotification($permit, 'review_needed'));
        } else {
            $permit->user->notify(new ExitPermitNotification($permit, 'rejected'));
        }

        return $permit;
    }

    public function decide(DormExitPermit $permit, User $manager, bool $approve, ?string $note = null): DormExitPermit
    {
        $ready = $permit->guardian_required ? $permit->status === ExitPermitStatus::GuardianApproved : $permit->status === ExitPermitStatus::Submitted;

        if (! $ready) {
            throw new InvalidArgumentException($permit->guardian_required && $permit->status === ExitPermitStatus::Submitted
                ? 'Menunggu persetujuan wali terlebih dulu.'
                : 'Izin ini sudah diputuskan.');
        }

        if (! $approve && blank($note)) {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $permit->update([
            'status' => $approve ? ExitPermitStatus::Approved : ExitPermitStatus::Rejected,
            'approved_by' => $manager->id,
            'decided_at' => now(),
            'approval_note' => filled($note) ? trim($note) : null,
            'qr_token' => $approve ? Str::random(40) : null,
        ]);

        $permit->user->notify(new ExitPermitNotification($permit, $approve ? 'approved' : 'rejected'));
        Notification::send($permit->user->guardians()->get(), new ExitPermitNotification($permit, 'guardian_info'));

        return $permit;
    }

    /**
     * Pindai QR izin: pertama kali = keluar, berikutnya = kembali.
     *
     * @return array{permit: DormExitPermit, direction: string}
     */
    public function scan(string $token, User $officer): array
    {
        $permit = DormExitPermit::query()->with('user')->where('qr_token', trim($token))->first();

        if (! $permit) {
            throw new InvalidArgumentException('QR izin tidak dikenal.');
        }

        if ($permit->status === ExitPermitStatus::Approved) {
            if (now()->lt($permit->planned_departure->subHour())) {
                throw new InvalidArgumentException('Belum waktunya keluar (rencana '.tanggal_jam($permit->planned_departure).' WITA).');
            }

            $permit->update(['status' => ExitPermitStatus::Out, 'actual_departure' => now(), 'departure_scanned_by' => $officer->id]);

            return ['permit' => $permit, 'direction' => 'out'];
        }

        if ($permit->status->isOutside()) {
            $late = now()->gt($permit->planned_return);
            $permit->update([
                'status' => ExitPermitStatus::Returned,
                'actual_return' => now(),
                'return_scanned_by' => $officer->id,
                'was_late' => $permit->was_late || $late,
            ]);

            if ($permit->was_late) {
                Notification::send($permit->user->guardians()->get(), new ExitPermitNotification($permit, 'returned'));
            }

            return ['permit' => $permit, 'direction' => 'in'];
        }

        throw new InvalidArgumentException('Izin ini tidak aktif ('.$permit->status->label().').');
    }

    public function cancel(DormExitPermit $permit, User $participant): DormExitPermit
    {
        if ($permit->user_id !== $participant->id) {
            throw new InvalidArgumentException('Bukan izin milik ngana.');
        }

        if (! $permit->status->isPending() && $permit->status !== ExitPermitStatus::Approved) {
            throw new InvalidArgumentException('Izin yang sudah dipakai keluar tidak bisa dibatalkan.');
        }

        $permit->update(['status' => ExitPermitStatus::Cancelled, 'qr_token' => null]);

        return $permit;
    }

    /**
     * Tandai izin yang melewati rencana kembali menjadi "terlambat kembali" dan kabari pengelola & wali.
     */
    public function markLateReturns(): int
    {
        $late = DormExitPermit::query()->with('user')
            ->where('status', ExitPermitStatus::Out)
            ->where('planned_return', '<', now())
            ->get();

        foreach ($late as $permit) {
            $permit->update(['status' => ExitPermitStatus::LateReturn, 'was_late' => true, 'late_notified_at' => now()]);
            Notification::send($this->managers()->merge($permit->user->guardians()->get()), new ExitPermitNotification($permit, 'late'));
            event(new ExitPermitReturnedLate($permit));
        }

        return $late->count();
    }

    protected function conflictingSession(User $participant, CarbonImmutable $from, CarbonImmutable $to): ?LearningSession
    {
        $classroomIds = $participant->classrooms()->pluck('classrooms.id');

        return LearningSession::query()->whereIn('classroom_id', $classroomIds)
            ->whereDateBetween('date', $from->toDateString(), $to->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')->orderBy('start_time')->get()
            ->first(fn (LearningSession $session) => $session->startsAt()->lt($to) && $session->endsAt()->gt($from));
    }

    /** Pengelola asrama (tim kerja unit asrama). */
    protected function managers()
    {
        return User::query()->inUnits(StaffUnit::Dormitory)->get();
    }

    /** Penyetuju izin keluar: pengelola asrama + admin program. */
    protected function approvers()
    {
        return $this->managers()
            ->merge(User::query()->active()->role(Role::ProgramAdmin->value)->get())
            ->unique('id')->values();
    }
}
