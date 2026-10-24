@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Wokabulari" {{ $attributes->class('font-display') }}>
        <x-slot name="logo" class="flex aspect-square size-9 items-center justify-center">
            <x-app-logo-icon class="size-9" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <a {{ $attributes->class('flex items-center gap-2') }}>
        <x-app-logo-icon class="size-8" />
        <span class="font-display text-xl font-extrabold leading-none tracking-tight">
            <span class="text-woka dark:text-woka-light">Woka</span><span class="text-hutan dark:text-santan">bulari</span>
        </span>
    </a>
@endif
