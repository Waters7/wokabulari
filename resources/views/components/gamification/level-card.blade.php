{{-- Kartu level + streak (dipakai di beranda peserta, halaman poin, dan portal wali). --}}
@props(['progress', 'streak' => null, 'week' => null, 'name' => null])

@php($level = $progress['level'])
<section {{ $attributes->class('overflow-hidden rounded-3xl bg-kemenkes-daun p-5 text-hutan') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-hutan/70">{{ $name ? 'Level '.$name : 'Level ngana' }}</p>
            <p class="font-display text-3xl font-extrabold leading-tight">{{ $level?->name ?? 'Kemangi' }}</p>
            <p class="text-sm text-hutan/80">{{ angka($progress['points']) }} poin @if ($week !== null) · +{{ angka($week) }} minggu ini @endif</p>
        </div>
        @if ($streak)
            <div class="shrink-0 text-center" title="Streak terpanjang {{ $streak->longest_streak }} hari">
                <span @class(['inline-flex size-14 items-center justify-center rounded-2xl bg-white/35', 'animate-flame' => $streak->current_streak > 0])>
                    <flux:icon.fire variant="solid" class="size-9 {{ $streak->current_streak > 0 ? 'text-orange-500' : 'text-hutan/30' }}" />
                </span>
                <p class="mt-1 text-xs font-bold">{{ $streak->current_streak }} hari</p>
            </div>
        @endif
    </div>
    <div class="mt-4">
        <div class="h-3 overflow-hidden rounded-full bg-white/45" role="progressbar" aria-valuenow="{{ $progress['progress'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-hutan transition-all" style="width: {{ $progress['progress'] }}%"></div>
        </div>
        <p class="mt-1 text-xs text-hutan/80">
            @if ($progress['next'])
                {{ angka($progress['remaining']) }} poin lagi menuju {{ $progress['next']->name }}
            @else
                Level tertinggi — Woku Komplit!
            @endif
        </p>
    </div>
</section>
