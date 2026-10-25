<?php

use App\Actions\Sessions\SubmitSessionCode;
use App\Enums\SessionStatus;
use App\Models\DailyAttendance;
use App\Models\LearningSession;
use App\Models\SessionAttendance;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Input kode sesi')] class extends Component {
    #[Url(as: 'kode')]
    public string $code = '';

    public ?string $error = null;

    public ?array $success = null;

    public function mount(): void
    {
        $this->code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $this->code), 0, 6));
    }

    /** Terima hasil pindai QR: bisa berupa URL ?kode=XXXXXX atau kode langsung. */
    public function fromQr(string $text): void
    {
        $query = parse_url($text, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        $this->code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $params['kode'] ?? $text), 0, 6));
        $this->submit(app(SubmitSessionCode::class));
    }

    public function submit(SubmitSessionCode $submit, ?float $latitude = null, ?float $longitude = null): void
    {
        $this->error = null;
        $this->success = null;

        try {
            $attendance = $submit->handle(auth()->user(), $this->code, ['latitude' => $latitude, 'longitude' => $longitude]);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $session = $attendance->session;
        $this->success = ['topic' => $session->topic, 'class' => $session->classroom->label(), 'time' => jam($attendance->submitted_at)];
        $this->code = '';
        Flux::toast(variant: 'success', text: 'Mantap! Kehadiran sesi tercatat.');
    }

    public function with(): array
    {
        $user = auth()->user();
        $classroomIds = $user->classrooms()->pluck('classrooms.id');

        return [
            'dailyDone' => DailyAttendance::query()->where('user_id', $user->id)->whereDate('date', today())->whereIn('status', ['present', 'late'])->exists(),
            'todaySessions' => LearningSession::query()->whereIn('classroom_id', $classroomIds)->whereDate('date', today())
                ->where('status', '!=', SessionStatus::Cancelled)->orderBy('start_time')->get(),
            'myRecords' => SessionAttendance::query()->where('user_id', $user->id)->whereHas('session', fn ($q) => $q->whereDate('date', today()))->get()->keyBy('learning_session_id'),
            'needsLocation' => (bool) setting('session.validate_location'),
        ];
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Input kode sesi" subtitle="Ketik 6 karakter dari pengajar atau pindai QR di layar." />

    @unless ($dailyDone)
        <flux:callout icon="camera" color="amber" class="mb-4" heading="Absen harian dulu" text="Kode sesi hanya bisa dimasukkan setelah absen harian hari ini.">
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" :href="route('attendance.daily')" wire:navigate>Absen sekarang</flux:button>
            </x-slot>
        </flux:callout>
    @endunless

    @if ($success)
        <x-card class="mb-4 text-center">
            <div class="mx-auto mb-2 flex size-14 animate-pop-in items-center justify-center rounded-full bg-woka-50 text-woka"><flux:icon.check class="size-8" /></div>
            <p class="font-display text-lg font-bold">Hadir di sesi {{ $success['topic'] }}</p>
            <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $success['class'] }} · pukul {{ $success['time'] }}</p>
        </x-card>
    @endif

    <x-card x-data="qrScanner(function (text) { $wire.fromQr(text) })">
        <form @if ($needsLocation)
                  x-data="geolocate()" x-on:submit.prevent="locate().then(c => $wire.submit(c.latitude, c.longitude)).catch(() => $wire.submit())"
              @else
                  wire:submit="submit"
              @endif class="space-y-4">
            <flux:input wire:model="code" label="Kode sesi" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false"
                        class:input="text-center font-mono text-3xl! tracking-[0.4em] uppercase h-16!" placeholder="••••••"
                        x-on:input="$event.target.value = $event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '')" />

            @if ($error)
                <flux:callout icon="exclamation-triangle" color="red" :text="$error" />
            @endif

            <flux:button type="submit" variant="primary" class="w-full" icon="check">Kirim kode</flux:button>
        </form>

        <flux:separator text="atau" class="my-4" />

        <div x-show="scanning" x-cloak class="mb-3 overflow-hidden rounded-xl bg-hutan">
            <div id="qr-reader" x-ref="reader" wire:ignore></div>
        </div>
        <p x-show="error" x-text="error" class="mb-2 text-sm text-rica"></p>
        <flux:button class="w-full" icon="qr-code" x-show="!scanning" x-on:click="start()">Pindai QR</flux:button>
        <flux:button class="w-full" variant="ghost" x-show="scanning" x-on:click="stop()">Batal pindai</flux:button>
    </x-card>

    @if ($todaySessions->isNotEmpty())
        <h2 class="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Sesi hari ini</h2>
        <div class="space-y-2">
            @foreach ($todaySessions as $s)
                <x-card wire:key="ts-{{ $s->id }}" class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $s->topic }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $s->timeRange() }} · <x-status-badge :status="$s->status" /></p>
                    </div>
                    @if ($r = $myRecords->get($s->id))
                        <x-status-badge :status="$r->status" />
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
