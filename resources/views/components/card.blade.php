@props(['title' => null, 'subtitle' => null, 'padding' => true, 'href' => null])

@php
    $tag = $href ? 'a' : 'section';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->class([
        'block rounded-2xl bg-white ring-1 ring-woka-100 shadow-xs dark:bg-hutan-900 dark:ring-hutan-800',
        'transition hover:ring-woka-200 hover:shadow-sm' => $href,
        'p-4 sm:p-5' => $padding,
    ]) }}>
    @if ($title || isset($action))
        <div @class(['mb-3 flex items-start justify-between gap-3', 'px-4 pt-4 sm:px-5 sm:pt-5' => ! $padding])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="font-display text-lg font-bold leading-tight text-hutan dark:text-santan">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($action)
                <div class="shrink-0">{{ $action }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</{{ $tag }}>
