@props(['title' => 'Belum ada data', 'description' => null, 'icon' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-2xl border border-dashed border-woka-200 bg-white/60 px-6 py-10 text-center dark:border-hutan-800 dark:bg-hutan-900/40') }}>
    @if ($icon)
        <span class="mb-3 flex size-14 items-center justify-center rounded-full bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
            <flux:icon :name="$icon" class="size-7" />
        </span>
    @else
        {{-- Ilustrasi sederhana: blanga woku --}}
        <svg class="mb-3 h-16 w-20 text-woka/70 dark:text-woka-light/70" viewBox="0 0 80 64" fill="none" aria-hidden="true">
            <path d="M30 14c0-4 4-4 4-8M42 14c0-4 4-4 4-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity=".5"/>
            <rect x="8" y="22" width="64" height="6" rx="3" fill="currentColor" opacity=".35"/>
            <path d="M12 28h56c0 16-12.5 28-28 28S12 44 12 28Z" fill="currentColor" opacity=".8"/>
            <path d="M22 36c10 4 26 4 36 0" stroke="#f5fbfa" stroke-width="3" stroke-linecap="round" opacity=".7"/>
        </svg>
    @endif
    <p class="font-display text-lg font-bold text-hutan dark:text-santan">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-hutan/60 dark:text-santan/60">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
