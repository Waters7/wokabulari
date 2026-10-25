{{-- Beranda peserta (bagian gamifikasi; dilengkapi di Fase 12). --}}
@php
    $points = app(\App\Services\Gamification\PointService::class);
    $me = auth()->user();
    $progress = app(\App\Services\Gamification\LevelService::class)->progress($points->total($me));
    $weekPoints = $points->between($me, now()->startOfWeek(), now()->endOfWeek());
    $best = app(\App\Services\Gamification\LeaderboardService::class)->bestOfWeek();
@endphp

<div class="space-y-4">
    <a href="{{ route('points.index') }}" wire:navigate class="block">
        <x-gamification.level-card :progress="$progress" :streak="$me->streak" :week="$weekPoints" />
    </a>

    @if ($best)
        <x-card :href="route('leaderboard')">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-kunyit text-hutan">
                    <flux:icon.trophy variant="solid" class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-kunyit-700 dark:text-kunyit">Peserta terbaik minggu ini</p>
                    <p class="truncate font-semibold">{{ $best['user']->is($me) ? 'Ngana! 🎉' : $best['user']->displayName() }}</p>
                </div>
                <span class="shrink-0 font-display text-lg font-bold">{{ angka($best['points']) }}</span>
            </div>
        </x-card>
    @endif
</div>
