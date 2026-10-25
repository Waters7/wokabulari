<?php

use App\Actions\Dorm\RecordDormAttendance;
use App\Enums\DormAttendanceStatus;
use App\Http\Requests\DailyAttendanceRequest;
use App\Models\DormAttendance;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Absen asrama')] class extends Component {
    public ?string $error = null;

    public int $attempt = 0;

    #[Computed]
    public function tonight(): ?DormAttendance
    {
        return DormAttendance::query()->where('user_id', auth()->id())->whereDate('date', today())->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(array $payload, RecordDormAttendance $record): void
    {
        $this->authorize('create', DormAttendance::class);
        $this->error = null;
        $this->attempt++;

        $validator = Validator::make($payload, DailyAttendanceRequest::rulesFor());
        if ($validator->fails()) {
            $this->error = 'Data absen tidak lengkap. Ulangi pengambilan wajah.';

            return;
        }

        try {
            $attendance = $record->handle(auth()->user(), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->tonight);

        $attendance->status === DormAttendanceStatus::Rejected
            ? Flux::toast(variant: 'danger', text: 'Absen ditolak: '.$attendance->reject_reason)
            : Flux::toast(variant: 'success', text: 'Absen asrama tercatat. Selamat istirahat!');
    }

    public function with(): array
    {
        $user = auth()->user();
        $dormitory = $user->currentDormitory();

        return [
            'dormitory' => $dormitory,
            'room' => $user->currentRoomAssignment?->room,
            'window' => $dormitory?->checkWindow(),
            'curfew' => $dormitory?->curfewAt(),
            'needsFace' => config('wokabulari.face_verification_enabled') && ! $user->profile?->hasFaceReference(),
            'history' => DormAttendance::query()->where('user_id', $user->id)->whereDate('date', '<', today())->latest('date')->limit(14)->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Absen asrama" :subtitle="tanggal_panjang(now())" />

    @php($tonight = $this->tonight)

    @if (! $dormitory)
        <x-empty-state icon="home-modern" title="Belum ada kamar asrama" description="Hubungi pengelola asrama agar ngana ditempatkan di kamar." />
    @elseif ($tonight && $tonight->status !== DormAttendanceStatus::Rejected)
        <x-card class="text-center">
            <div class="mx-auto mb-3 flex size-16 items-center justify-center rounded-full bg-woka-50 text-woka dark:bg-hutan-800">
                <flux:icon :name="$tonight->status === DormAttendanceStatus::SickInRoom ? 'heart' : 'moon'" class="size-10" />
            </div>
            <p class="font-display text-xl font-bold">Status malam ini</p>
            <div class="mt-2"><x-status-badge :status="$tonight->status" size="lg" /></div>
            @if ($tonight->checked_in_at && $tonight->attempts > 0)
                <p class="mt-2 text-sm text-hutan/70 dark:text-santan/70">Absen pukul {{ jam($tonight->checked_in_at) }} WITA</p>
            @endif
            @if ($tonight->status === DormAttendanceStatus::PendingConfirmation)
                <p class="mt-2 text-sm text-hutan/70 dark:text-santan/70">Pengelola asrama akan mengonfirmasi kehadiranmu.</p>
            @endif
            @if ($tonight->note)
                <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">{{ $tonight->note }}</p>
            @endif
        </x-card>
    @elseif ($needsFace)
        <flux:callout icon="face-smile" color="amber" heading="Daftarkan wajah dulu" text="Absen asrama memakai verifikasi wajah yang sama dengan absen harian." />
        <flux:button class="mt-3 w-full" variant="primary" icon="face-smile" :href="route('face.enroll')" wire:navigate>Daftarkan wajah</flux:button>
    @else
        <x-card class="mb-4">
            <div class="flex items-center gap-3 text-sm">
                <flux:icon.home-modern class="size-6 shrink-0 text-woka" />
                <div>
                    <p class="font-semibold">{{ $dormitory->name }}@if ($room) · Kamar {{ $room->number }}@endif</p>
                    <p class="text-hutan/60 dark:text-santan/60">Absen {{ jam($window[0]) }}–{{ jam($window[1]) }} WITA · jam malam {{ jam($curfew) }}</p>
                </div>
            </div>
        </x-card>

        @if ($tonight?->status === DormAttendanceStatus::Rejected)
            <flux:callout icon="x-circle" color="red" class="mb-4" heading="Absen sebelumnya ditolak" :text="$tonight->reject_reason.' Silakan coba lagi.'" />
        @endif

        @if ($error)
            <flux:callout icon="exclamation-triangle" color="red" class="mb-4" :text="$error" />
        @endif

        @if (now()->between($window[0], $window[1]))
            <x-face-camera mode="verify" action="submit" :needs-location="true" start-label="Mulai absen asrama" wire:key="dorm-{{ $attempt }}" />
            <p class="mt-4 text-center text-xs text-hutan/60 dark:text-santan/60">Pastikan ngana berada di area asrama dan GPS aktif.</p>
        @else
            <x-empty-state icon="clock" :title="now()->lt($window[0]) ? 'Absen belum dibuka' : 'Jendela absen sudah ditutup'"
                           :description="now()->lt($window[0]) ? 'Absen asrama dibuka pukul '.jam($window[0]).' WITA.' : 'Belum absen? Lapor ke pengelola asrama untuk apel manual.'" />
        @endif
    @endif

    @if ($history->isNotEmpty())
        <h2 class="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">14 malam terakhir</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($history as $item)
                <div class="flex items-center justify-between px-4 py-2.5 text-sm" wire:key="dh-{{ $item->id }}">
                    <span>{{ tanggal($item->date, 'D, j M') }}</span>
                    <x-status-badge :status="$item->status" />
                </div>
            @endforeach
        </x-card>
    @endif
</div>
