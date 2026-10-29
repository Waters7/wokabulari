{{-- Kartu level + streak (dipakai di beranda peserta, halaman poin, dan portal wali). Gaya minimalis: kartu datar, satu aksen. --}}
@props(['progress', 'streak' => null, 'week' => null, 'name' => null])

@php($level = $progress['level'])
<section {{ $attributes->class('rounded-2xl bg-white p-5 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">{{ $name ? 'Level '.$name : 'Level ngana' }}</p>
            <p class="mt-1 font-display text-2xl font-extrabold leading-tight">{{ $level?->name ?? 'Kemangi' }}</p>
            <p class="text-sm text-hutan/60 dark:text-santan/60">
                {{ angka($progress['points']) }} poin @if ($week !== null)<span class="text-woka dark:text-woka-light">· +{{ angka($week) }} minggu ini</span>@endif
            </p>
        </div>
        @if ($streak)
            <div class="flex shrink-0 items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-sm font-semibold text-orange-700 dark:bg-orange-500/10 dark:text-orange-300"
                 title="Streak terpanjang {{ $streak->longest_streak }} hari">
                <flux:icon.fire variant="micro" @class(['size-4', 'animate-flame' => $streak->current_streak > 0, 'opacity-40' => $streak->current_streak === 0]) />
                {{ $streak->current_streak }} hari
            </div>
        @endif
    </div>
    <div class="mt-4">
        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-hutan-800" role="progressbar" aria-valuenow="{{ $progress['progress'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-woka transition-all dark:bg-woka-light" style="width: {{ $progress['progress'] }}%"></div>
        </div>
        <p class="mt-1.5 text-xs text-hutan/50 dark:text-santan/50">
            @if ($progress['next'])
                {{ angka($progress['remaining']) }} poin lagi menuju {{ $progress['next']->name }}
            @else
                Level tertinggi — Woku Komplit!
            @endif
        </p>
    </div>
</section>
