@props(['count' => 0])

@if (Route::has('notifications.index'))
    <a href="{{ route('notifications.index') }}" wire:navigate
       class="relative inline-flex size-11 items-center justify-center rounded-full text-hutan hover:bg-woka-50 dark:text-santan dark:hover:bg-hutan-800"
       aria-label="Notifikasi{{ $count ? " ($count belum dibaca)" : '' }}">
        <flux:icon.bell class="size-6" />
        @if ($count > 0)
            <span class="absolute end-1.5 top-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-rica px-1 text-[11px] font-bold leading-5 text-white">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </a>
@endif
