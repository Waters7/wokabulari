@props(['title', 'subtitle' => null, 'back' => null])

<div {{ $attributes->class('mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-sm font-semibold text-woka hover:underline dark:text-woka-light">
                <flux:icon.arrow-left class="size-4" /> Kembali
            </a>
        @endif
        <h1 class="font-display text-2xl font-extrabold leading-tight text-hutan sm:text-3xl dark:text-santan">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
