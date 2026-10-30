<?php

use App\Actions\Attendance\RecordDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\PointEvent;
use App\Http\Requests\DailyAttendanceRequest;
use App\Models\DailyAttendance;
use App\Services\Gamification\PointService;
use App\Support\AttendanceSchedule;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Absen harian')] class extends Component {
    public ?string $error = null;

    public int $attempt = 0;

    #[Computed]
    public function today(): ?DailyAttendance
    {
        return DailyAttendance::query()->where('user_id', auth()->id())->whereDate('date', today())->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(array $payload, RecordDailyAttendance $record): void
    {
        $this->authorize('create', DailyAttendance::class);
        $this->error = null;

        $validator = Validator::make($payload, DailyAttendanceRequest::rulesFor());
        if ($validator->fails()) {
            $this->error = 'Data absen tidak lengkap. Ulangi pengambilan wajah.';
            $this->attempt++;

            return;
        }

        try {
            $attendance = $record->handle(auth()->user(), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            $this->attempt++;

            return;
        }

        unset($this->today);
        $this->attempt++;

        if ($attendance->isAttended()) {
            $penalty = app(PointService::class)->penaltyLabel(PointEvent::DailyAttendanceLate);
            Flux::toast(variant: $attendance->status === AttendanceStatus::Late ? 'warning' : 'success', text: $attendance->status === AttendanceStatus::Late
                ? 'Absen tercatat TERLAMBAT'.($penalty ? ' ('.$penalty.')' : '').'. Besok lebih pagi neh!'
                : 'Mantap! Absen tercatat. Selamat blajar!');
        } else {
            Flux::toast(variant: 'danger', text: 'Absen ditolak: '.$attendance->reject_reason);
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $classroom = $user->activeClassroom();
        $schedule = AttendanceSchedule::forBatch($classroom?->batch);
        $now = now();

        return [
            'classroom' => $classroom,
            'needsFace' => config('wokabulari.face_verification_enabled') && ! $user->profile?->hasFaceReference(),
            'schedule' => $schedule,
            'late' => $now->gt($schedule->lateAfterOn($now)),
            'penalty' => app(PointService::class)->penaltyLabel(PointEvent::DailyAttendanceLate),
            // Status jadwal hari ini: off (bukan hari absen), early (belum dibuka), closed (sudah ditutup), open.
            'window' => match (true) {
                ! $schedule->isScheduledDay($now) => 'off',
                $now->lt($schedule->opensOn($now)) => 'early',
                ($closes = $schedule->closesOn($now)) && $now->gt($closes) => 'closed',
                default => 'open',
            },
        ];
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Absen harian" :subtitle="tanggal_panjang(now())" />

    @php($today = $this->today)

    @if ($today && $today->isAttended())
        <x-card class="text-center">
            <div class="mx-auto mb-3 flex size-16 animate-pop-in items-center justify-center rounded-full bg-woka-50 text-woka dark:bg-hutan-800">
                <flux:icon.check-badge class="size-10" />
            </div>
            <p class="font-display text-xl font-bold">Ngana so absen hari ini!</p>
            <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">Tercatat pukul {{ jam($today->checked_in_at) }} WITA</p>
            <div class="mt-3"><x-status-badge :status="$today->status" size="lg" /></div>
            @if ($today->status === AttendanceStatus::Late)
                <p class="mt-2 text-sm font-semibold text-rica" data-test="late-notice">Tercatat terlambat (lewat {{ jam($schedule->lateAfter) }} WITA){{ $penalty ? ' · '.$penalty : '' }}.</p>
            @endif
            @if ($today->approval_note)
                <p class="mt-3 text-xs text-hutan/60 dark:text-santan/60">Ditandai manual: {{ $today->approval_note }}</p>
            @endif
            <div class="mt-5 grid gap-2">
                @if (Route::has('sessions.code'))
                    <flux:button variant="primary" icon="qr-code" :href="route('sessions.code')" wire:navigate>Input kode sesi</flux:button>
                @endif
                <flux:button icon="calendar-days" :href="route('attendance.history')" wire:navigate>Riwayat absen</flux:button>
            </div>
        </x-card>
    @elseif ($today && in_array($today->status, [AttendanceStatus::Excused, AttendanceStatus::Sick, AttendanceStatus::Absent], true))
        <x-card class="text-center">
            <p class="font-display text-xl font-bold">Status hari ini</p>
            <div class="mt-3"><x-status-badge :status="$today->status" size="lg" /></div>
            <p class="mt-2 text-sm text-hutan/70 dark:text-santan/70">{{ $today->approval_note }}</p>
        </x-card>
    @elseif (! $classroom)
        <x-empty-state icon="building-library" title="Belum terdaftar di kelas" description="Hubungi admin program agar ngana dimasukkan ke kelas." />
    @elseif ($window === 'off')
        <x-empty-state icon="calendar-days" title="Hari ini tidak ada absen harian"
                       :description="'Jadwal absen angkatan ngana: '.$schedule->daysLabel().'. Absen berikutnya '.tanggal($schedule->nextScheduledDay(now()), 'l, j M').', '.jam($schedule->opensAt).' WITA.'" />
    @elseif ($window === 'early')
        <x-empty-state icon="clock" title="Absen belum dibuka"
                       :description="'Absen harian dibuka pukul '.jam($schedule->opensAt).' WITA ('.$schedule->hoursLabel().').'" />
    @elseif ($window === 'closed')
        <x-empty-state icon="lock-closed" title="Absen hari ini sudah ditutup"
                       :description="'Ditutup pukul '.jam($schedule->closesAt).' WITA. Hubungi pengajar bila ada kendala.'" />
    @elseif ($needsFace)
        <flux:callout icon="face-smile" color="amber" heading="Daftarkan wajah dulu" text="Sebelum absen pertama, daftarkan wajah referensi (sekali saja)." />
        <flux:button class="mt-3 w-full" variant="primary" icon="face-smile" :href="route('face.enroll')" wire:navigate>Daftarkan wajah</flux:button>
    @else
        <x-card class="mb-4">
            <div class="flex items-center gap-3 text-sm">
                <flux:icon.map-pin class="size-6 shrink-0 text-woka" />
                <div>
                    <p class="font-semibold">{{ $classroom->label() }}</p>
                    <p class="text-hutan/60 dark:text-santan/60">{{ $classroom->location?->name ?? 'Lokasi kelas' }} · tepat waktu s.d. {{ jam($schedule->lateAfter) }}{{ $schedule->closesAt ? ', ditutup '.jam($schedule->closesAt) : '' }} WITA</p>
                </div>
            </div>
        </x-card>

        @if ($late)
            <flux:callout icon="clock" color="amber" class="mb-4" data-test="late-warning" heading="Sudah lewat batas tepat waktu"
                          :text="'Batas tepat waktu pukul '.jam($schedule->lateAfter).' WITA. Absen tetap bisa, tetapi tercatat TERLAMBAT'.($penalty ? ' dan poin berkurang ('.$penalty.')' : '').'.'" />
        @endif

        @if ($today?->status === AttendanceStatus::Rejected)
            <flux:callout icon="x-circle" color="red" class="mb-4" heading="Absen sebelumnya ditolak" :text="$today->reject_reason.' Silakan coba lagi.'" />
        @endif

        @if ($error)
            <flux:callout icon="exclamation-triangle" color="red" class="mb-4" :text="$error" />
        @endif

        <x-face-camera mode="verify" action="submit" :needs-location="true" start-label="Mulai absen" wire:key="absen-{{ $attempt }}" />

        <p class="mt-4 text-center text-xs text-hutan/60 dark:text-santan/60">
            Pastikan GPS aktif dan ngana berada di area kelas. Selfie disimpan privat dan hanya dilihat petugas.
        </p>
    @endif
</div>
