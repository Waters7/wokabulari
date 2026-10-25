<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Models\DormExitPermit;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pindai QR izin')] class extends Component {
    public string $token = '';

    public ?string $error = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('scan', DormExitPermit::class);
    }

    public function fromQr(string $text, ManageExitPermits $permits): void
    {
        $this->token = trim($text);
        $this->scan($permits);
    }

    public function scan(ManageExitPermits $permits): void
    {
        $this->authorize('scan', DormExitPermit::class);
        $this->error = null;
        $this->result = null;

        try {
            ['permit' => $permit, 'direction' => $direction] = $permits->scan($this->token, auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->result = [
            'name' => $permit->user->name,
            'direction' => $direction,
            'type' => $permit->type->label(),
            'destination' => $permit->destination,
            'period' => $permit->periodLabel(),
            'late' => $permit->was_late,
            'time' => jam(now()),
        ];
        $this->token = '';
        Flux::toast(variant: 'success', text: $direction === 'out' ? $permit->user->name.' tercatat keluar.' : $permit->user->name.' tercatat kembali.');
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Pindai QR izin" subtitle="Pindai saat peserta keluar dan saat kembali ke asrama." :back="route('dorm.manage.exits')" />

    @if ($result)
        <x-card class="mb-4 text-center" wire:key="res-{{ $result['time'] }}-{{ $result['name'] }}">
            <div @class(['mx-auto mb-3 flex size-16 items-center justify-center rounded-full', 'bg-sky-100 text-sky-700' => $result['direction'] === 'out', 'bg-woka-50 text-woka' => $result['direction'] === 'in'])>
                <flux:icon :name="$result['direction'] === 'out' ? 'arrow-right-start-on-rectangle' : 'arrow-left-end-on-rectangle'" class="size-9" />
            </div>
            <p class="font-display text-xl font-bold">{{ $result['name'] }}</p>
            <p class="text-sm">{{ $result['direction'] === 'out' ? 'KELUAR' : 'KEMBALI' }} pukul {{ $result['time'] }} WITA</p>
            <p class="mt-1 text-sm text-hutan/60 dark:text-santan/60">{{ $result['type'] }} · {{ $result['destination'] }}</p>
            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $result['period'] }}</p>
            @if ($result['direction'] === 'in' && $result['late'])
                <flux:badge color="rose" class="mt-2">Terlambat kembali</flux:badge>
            @endif
        </x-card>
    @endif

    <x-card x-data="qrScanner(function (text) { $wire.fromQr(text) })">
        <div x-show="scanning" x-cloak class="mb-3 overflow-hidden rounded-xl bg-hutan">
            <div id="exit-qr-reader" x-ref="reader" wire:ignore></div>
        </div>
        <p x-show="error" x-text="error" class="mb-2 text-sm text-rica"></p>
        <flux:button class="w-full" variant="primary" icon="qr-code" x-show="!scanning" x-on:click="start()">Mulai pindai</flux:button>
        <flux:button class="w-full" variant="ghost" x-show="scanning" x-on:click="stop()">Batal pindai</flux:button>

        <flux:separator text="atau ketik kode" class="my-4" />

        <form wire:submit="scan" class="flex gap-2">
            <flux:input wire:model="token" placeholder="Kode QR izin" autocomplete="off" class="flex-1" />
            <flux:button type="submit">Cek</flux:button>
        </form>
        @if ($error)
            <flux:callout icon="exclamation-triangle" color="red" class="mt-3" :text="$error" />
        @endif
    </x-card>
</div>
