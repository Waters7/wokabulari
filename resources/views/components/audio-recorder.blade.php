{{-- Perekam suara; hasil rekaman diunggah ke properti Livewire yang disebut di "model". --}}
@props(['model', 'label' => 'Rekam suara', 'maxSeconds' => 300])

<div x-data="audioRecorder({ model: @js($model), maxSeconds: {{ (int) $maxSeconds }} })" wire:ignore
     {{ $attributes->class('rounded-xl bg-santan p-3 ring-1 ring-woka-100 dark:bg-hutan-950 dark:ring-hutan-800') }}>
    <p class="mb-2 text-sm font-semibold">{{ $label }}</p>

    <template x-if="!supported">
        <p class="text-sm text-rica">Perangkat/browser ini belum mendukung perekaman suara. Unggah berkas audio sebagai gantinya.</p>
    </template>

    <div x-show="supported" class="flex flex-wrap items-center gap-2">
        <flux:button size="sm" variant="primary" icon="microphone" x-show="state === 'idle' || state === 'error'" x-on:click="start()">Mulai rekam</flux:button>
        <flux:button size="sm" variant="danger" icon="stop" x-show="state === 'recording'" x-on:click="stop()">Berhenti</flux:button>
        <span x-show="state === 'recording'" class="flex items-center gap-1.5 text-sm font-semibold text-rica">
            <span class="size-2.5 animate-pulse rounded-full bg-rica"></span><span x-text="display"></span>
        </span>
        <audio x-show="url" :src="url" controls class="h-10 max-w-full"></audio>
        <span x-show="state === 'uploading'" class="text-xs text-woka">Mengunggah…</span>
        <span x-show="state === 'uploaded'" class="text-xs font-semibold text-woka">✓ Siap dikirim</span>
        <flux:button size="sm" variant="ghost" icon="arrow-path" x-show="state === 'uploaded'" x-on:click="reset()">Rekam ulang</flux:button>
    </div>
    <p x-show="error" x-text="error" class="mt-1 text-sm text-rica"></p>
</div>
