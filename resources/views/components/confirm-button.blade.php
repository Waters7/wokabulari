{{-- Tombol dengan modal konfirmasi. Contoh: <x-confirm-button action="delete(5)" /> --}}
@props([
    'action',
    'title' => 'Hapus data ini?',
    'message' => 'Tindakan ini tidak dapat dibatalkan.',
    'label' => null,
    'confirmLabel' => 'Ya, hapus',
    'icon' => 'trash',
    'variant' => 'danger',
    'size' => 'sm',
])

@php
    // "submit" → "submit()" agar aksi tanpa argumen benar-benar dipanggil.
    $call = str_contains($action, '(') ? $action : $action.'()';
@endphp

<div x-data="{ open: false }" class="inline-flex">
    <flux:button :size="$size" :variant="$label ? $variant : 'ghost'" :icon="$icon" x-on:click="open = true" {{ $attributes }}
                 :aria-label="$label ?? $title" :class="! $label && $variant === 'danger' ? 'text-rica!' : ''">
        {{ $label }}
    </flux:button>

    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[60] flex items-end justify-center bg-hutan/50 p-4 sm:items-center"
             x-on:keydown.escape.window="open = false" role="dialog" aria-modal="true">
            <div x-show="open" x-transition x-on:click.outside="open = false"
                 class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl dark:bg-hutan-900">
                <p class="font-display text-lg font-bold text-hutan dark:text-santan">{{ $title }}</p>
                <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">{{ $message }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="open = false">Batal</flux:button>
                    <flux:button :variant="$variant" x-on:click="open = false; $wire.{{ $call }}">{{ $confirmLabel }}</flux:button>
                </div>
            </div>
        </div>
    </template>
</div>
