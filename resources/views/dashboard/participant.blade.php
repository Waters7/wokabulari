{{-- Beranda peserta: level & streak, peserta terbaik minggu ini. --}}
@php
    $points = app(\App\Services\Gamification\PointService::class);
    $me = auth()->user();
    $progress = app(\App\Services\Gamification\LevelService::class)->progress($points->total($me));
    $weekPoints = $points->between($me, now()->startOfWeek(), now()->endOfWeek());
    $best = app(\App\Services\Gamification\LeaderboardService::class)->bestOfWeek();
@endphp

<div class="space-y-3">
    <a href="{{ route('points.index') }}" wire:navigate class="block transition hover:opacity-90">
        <x-gamification.level-card :progress="$progress" :streak="$me->streak" :week="$weekPoints" />
    </a>

    @if ($best)
        <a href="{{ route('leaderboard') }}" wire:navigate
           class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800">
            <flux:icon.trophy class="size-5 shrink-0 text-kunyit-700 dark:text-kunyit" />
            <span class="min-w-0 flex-1">
                <span class="block text-xs text-hutan/50 dark:text-santan/50">Peserta terbaik minggu ini</span>
                <span class="block truncate font-semibold">{{ $best['user']->is($me) ? 'Ngana! 🎉' : $best['user']->displayName() }}</span>
            </span>
            <span class="shrink-0 text-sm font-semibold tabular-nums">{{ angka($best['points']) }} poin</span>
        </a>
    @endif
</div>
