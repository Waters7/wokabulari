{{-- Kanvas tanda tangan. "action" = nama aksi Livewire; "args" = argumen sebelum data URI tanda tangan. --}}
@props(['action', 'args' => [], 'submitLabel' => 'Tanda tangani & terima', 'height' => 'h-48'])

<div x-data="signaturePad({ action: @js($action), args: @js($args) })" wire:ignore {{ $attributes->class('w-full') }}>
    <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-woka-200 bg-white dark:border-hutan-800">
        <canvas x-ref="canvas" class="{{ $height }} w-full touch-none" aria-label="Area tanda tangan"></canvas>
        <p x-show="empty" class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-hutan/40">Tanda tangan di sini</p>
    </div>
    {{ $slot }}
    <div class="mt-3 flex gap-2">
        <flux:button icon="arrow-path" x-on:click="clear()" x-bind:disabled="empty || sending">Hapus</flux:button>
        <flux:button variant="primary" icon="check" class="flex-1" x-on:click="submit()" x-bind:disabled="empty || sending">
            <span x-show="!sending">{{ $submitLabel }}</span><span x-show="sending">Mengirim…</span>
        </flux:button>
    </div>
</div>
