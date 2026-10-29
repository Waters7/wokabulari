{{-- Logo penyelenggara & mitra: Poltekkes Kemenkes Manado dan BP3MI Sulawesi Utara --}}
@props(['compact' => false, 'minimal' => false])

@php
    $bp3miFile = collect(['bp3mi.svg', 'bp3mi.png', 'bp3mi.webp', 'bp3mi.jpg'])
        ->first(fn ($file) => file_exists(public_path('images/logos/'.$file)));
@endphp

@if ($minimal)
    {{-- Varian minimalis (halaman login): tanpa bingkai, logo kecil berdampingan. --}}
    <div {{ $attributes->class('flex flex-col items-center gap-3') }}>
        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-hutan/40 dark:text-santan/40">Diselenggarakan oleh</p>
        <div class="flex items-center justify-center gap-5 rounded-xl dark:bg-white dark:px-3 dark:py-2">
            <img src="{{ asset('images/logos/poltekkes.png') }}" alt="Kemenkes Poltekkes Manado" class="h-8 w-auto max-w-[45%] object-contain">
            <span class="h-6 w-px shrink-0 bg-zinc-200" aria-hidden="true"></span>
            @if ($bp3miFile)
                <img src="{{ asset('images/logos/'.$bp3miFile) }}" alt="BP3MI Sulawesi Utara — Kementerian Pelindungan Pekerja Migran Indonesia" class="h-12 w-auto max-w-[55%] object-contain">
            @else
                <span class="text-[9px] font-bold uppercase leading-tight text-[#8a6a1f]">BP3MI Sulawesi Utara</span>
            @endif
        </div>
    </div>
@else
<div {{ $attributes->class('flex flex-col items-center gap-2') }}>
    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-hutan/60 dark:text-santan/60">
        Diselenggarakan oleh
    </p>
    {{-- Di layar sempit logo ditumpuk agar teks logo BP3MI tetap terbaca; berdampingan mulai lebar sm. --}}
    <div @class([
        'flex w-full flex-col items-center justify-center gap-3 rounded-2xl bg-white px-4 ring-1 ring-woka-100 sm:flex-row sm:gap-4 dark:ring-hutan-800',
        'py-2' => $compact,
        'py-3' => ! $compact,
    ])>
        <img src="{{ asset('images/logos/poltekkes.png') }}"
             alt="Kemenkes Poltekkes Manado"
             @class(['h-auto w-auto object-contain', 'max-h-12 sm:max-h-8 sm:max-w-[45%]' => $compact, 'max-h-11 sm:max-w-[46%]' => ! $compact])>

        <span class="h-px w-full shrink-0 bg-zinc-200 sm:h-8 sm:w-px" aria-hidden="true"></span>

        @if ($bp3miFile)
            <img src="{{ asset('images/logos/'.$bp3miFile) }}"
                 alt="BP3MI Sulawesi Utara — Kementerian Pelindungan Pekerja Migran Indonesia"
                 @class(['h-auto w-auto max-w-full object-contain', 'max-h-20 sm:max-h-10 sm:max-w-[50%]' => $compact, 'max-h-24 sm:max-h-14 sm:max-w-[52%]' => ! $compact])>
        @else
            {{-- Letakkan file logo di public/images/logos/bp3mi.png agar tampil di sini --}}
            <span class="max-w-[46%] text-start text-[10px] font-bold uppercase leading-tight text-[#8a6a1f]">
                Kementerian Pelindungan<br>Pekerja Migran Indonesia/BP2MI<br>
                <span class="text-hutan">BP3MI Sulawesi Utara</span>
            </span>
        @endif
    </div>
</div>
@endif
