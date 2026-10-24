@props(['label', 'value', 'icon' => null, 'tone' => 'woka', 'hint' => null, 'href' => null])

@php
    $tones = [
        'woka' => 'bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light',
        'kunyit' => 'bg-kunyit-50 text-kunyit-700 dark:bg-hutan-800 dark:text-kunyit',
        'rica' => 'bg-rica-50 text-rica dark:bg-hutan-800 dark:text-rica-100',
        'hutan' => 'bg-santan-200 text-hutan dark:bg-hutan-800 dark:text-santan',
    ];
@endphp

<x-card :href="$href" {{ $attributes->class('flex items-center gap-3') }}>
    @if ($icon)
        <span @class(['flex size-11 shrink-0 items-center justify-center rounded-xl', $tones[$tone] ?? $tones['woka']])>
            <flux:icon :name="$icon" class="size-6" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-xs font-semibold uppercase tracking-wide text-hutan/60 dark:text-santan/60">{{ $label }}</p>
        <p class="font-display text-2xl font-extrabold leading-tight text-hutan dark:text-santan">{{ $value }}</p>
        @if ($hint)
            <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $hint }}</p>
        @endif
    </div>
</x-card>
