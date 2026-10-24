{{-- Logo penyelenggara & mitra: Poltekkes Kemenkes Manado dan BP3MI Sulawesi Utara --}}
@props(['compact' => false])

@php
    $bp3miFile = collect(['bp3mi.svg', 'bp3mi.png', 'bp3mi.webp', 'bp3mi.jpg'])
        ->first(fn ($file) => file_exists(public_path('images/logos/'.$file)));
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-2') }}>
    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-hutan/60 dark:text-santan/60">
        Diselenggarakan oleh
    </p>
    <div @class([
        'flex w-full items-center justify-center gap-4 rounded-2xl bg-white px-4 ring-1 ring-woka-100 dark:ring-hutan-800',
        'py-2' => $compact,
        'py-3' => ! $compact,
    ])>
        <img src="{{ asset('images/logos/poltekkes.png') }}"
             alt="Kemenkes Poltekkes Manado"
             @class(['h-auto w-auto object-contain', 'max-h-8 max-w-[45%]' => $compact, 'max-h-11 max-w-[46%]' => ! $compact])>

        <span class="h-8 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>

        @if ($bp3miFile)
            <img src="{{ asset('images/logos/'.$bp3miFile) }}"
                 alt="BP3MI Sulawesi Utara — Kementerian Pelindungan Pekerja Migran Indonesia"
                 @class(['h-auto w-auto object-contain', 'max-h-9 max-w-[45%]' => $compact, 'max-h-12 max-w-[46%]' => ! $compact])>
        @else
            {{-- Letakkan file logo di public/images/logos/bp3mi.png agar tampil di sini --}}
            <span class="max-w-[46%] text-start text-[10px] font-bold uppercase leading-tight text-[#8a6a1f]">
                Kementerian Pelindungan<br>Pekerja Migran Indonesia/BP2MI<br>
                <span class="text-hutan">BP3MI Sulawesi Utara</span>
            </span>
        @endif
    </div>
</div>
