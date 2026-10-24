{{-- Navigasi bawah HP (maks. 5 item) untuk peserta, wali, dan tim kerja --}}
@props(['items' => []])

<nav class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-woka-100 bg-white/95 backdrop-blur lg:hidden dark:border-hutan-800 dark:bg-hutan-900/95"
     aria-label="Navigasi utama">
    <ul class="mx-auto grid max-w-lg" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr));">
        @foreach ($items as $item)
            <li>
                @if (($item['action'] ?? null) === 'menu')
                    <button type="button" x-data x-on:click="$dispatch('flux-sidebar-toggle')"
                            class="flex h-16 w-full flex-col items-center justify-center gap-0.5 text-[11px] font-semibold text-hutan/70 dark:text-santan/70">
                        <span class="flex h-7 w-12 items-center justify-center rounded-full">
                            <flux:icon.squares-2x2 class="size-6" />
                        </span>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </button>
                @else
                    <a href="{{ $item['url'] }}" wire:navigate
                       @class([
                           'flex h-16 flex-col items-center justify-center gap-0.5 text-[11px] font-semibold',
                           'text-woka dark:text-woka-light' => $item['active'],
                           'text-hutan/70 dark:text-santan/70' => ! $item['active'],
                       ])
                       @if ($item['active']) aria-current="page" @endif>
                        <span @class(['flex h-7 w-12 items-center justify-center rounded-full', 'bg-woka-50 dark:bg-hutan-800' => $item['active']])>
                            <flux:icon :name="$item['icon']" class="size-6" :variant="$item['active'] ? 'solid' : 'outline'" />
                        </span>
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
