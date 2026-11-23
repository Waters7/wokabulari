{{-- Galeri foto dokumentasi konsumsi (foto menu & foto total) di laporan Input konsumsi. --}}
@php
    $all = collect($gallery);
    $incomplete = $all->filter(fn ($schedule) => ! $schedule->delivery->menu_photo_path || ! $schedule->delivery->total_photo_path)->values();
    $items = $docFilter === 'missing' ? $incomplete : $all;
@endphp

@if ($all->isNotEmpty())
    <details open class="group mb-4" wire:key="rs-meal-gallery" data-test="meal-gallery">
        <summary class="mb-2 flex cursor-pointer list-none items-center gap-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">
            <flux:icon.chevron-right class="size-4 transition group-open:rotate-90" />
            Foto dokumentasi konsumsi <span class="font-normal normal-case">({{ $all->count() }} input)</span>
        </summary>

        <div class="mb-3 flex flex-wrap gap-1.5">
            @foreach (['' => 'Semua input ('.$all->count().')', 'missing' => 'Foto belum lengkap ('.$incomplete->count().')'] as $value => $text)
                <button type="button" wire:click="$set('docFilter', '{{ $value }}')" wire:key="mf-{{ $value ?: 'all' }}"
                        @class(['min-h-9 rounded-full px-3 text-sm font-semibold ring-1 transition',
                            'bg-woka text-white ring-woka' => $docFilter === $value,
                            'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $docFilter !== $value])>
                    {{ $text }}
                </button>
            @endforeach
        </div>

        @if ($items->isEmpty())
            <p class="rounded-2xl bg-white px-4 py-6 text-center text-sm text-hutan/60 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:text-santan/60 dark:ring-hutan-800">Semua input pada periode ini sudah ada fotonya.</p>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items->take($galleryLimit) as $schedule)
                    @php
                        $delivery = $schedule->delivery;
                    @endphp
                    <li wire:key="mg-{{ $schedule->id }}" class="min-w-0 rounded-2xl bg-white p-3 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                        <p class="truncate font-semibold">{{ $schedule->meal_type->label() }} · {{ $schedule->vendor?->name ?? '–' }}</p>
                        <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($schedule->date, 'D, j M') }} · {{ $delivery->quantityLabel() }} · {{ str_replace("\n", ', ', trim($delivery->menu)) }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            @foreach (['menu' => 'Foto menu', 'total' => 'Foto total'] as $kind => $label)
                                <div class="min-w-0" wire:key="mg-{{ $schedule->id }}-{{ $kind }}">
                                    <p class="text-xs font-semibold text-hutan/60 dark:text-santan/60">{{ $label }}</p>
                                    @if ($url = $delivery->photoUrl($kind))
                                        <a href="{{ $url }}" target="_blank" rel="noopener"
                                           class="mt-1 block aspect-[4/3] overflow-hidden rounded-lg bg-zinc-50 ring-1 ring-zinc-200 dark:bg-hutan-800 dark:ring-hutan-800">
                                            <img src="{{ $url }}" alt="{{ $label }} {{ $schedule->meal_type->label() }} {{ tanggal($schedule->date, 'j M') }}" class="size-full object-cover" loading="lazy">
                                        </a>
                                    @else
                                        <p class="mt-1 flex aspect-[4/3] items-center justify-center rounded-lg bg-rica/5 text-xs font-semibold text-rica ring-1 ring-rica/20">Belum ada</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($items->count() > $galleryLimit)
                <div class="mt-3 text-center">
                    <flux:button size="sm" wire:click="$set('galleryLimit', {{ $galleryLimit + 24 }})">Tampilkan lebih banyak ({{ $items->count() - $galleryLimit }} lagi)</flux:button>
                </div>
            @endif
        @endif
    </details>
@endif
