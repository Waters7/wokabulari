{{-- Portal wali: level, poin, streak, dan lencana anak. --}}
<div class="mb-4 space-y-3">
    <x-gamification.level-card :progress="$progress" :streak="$streak" :name="$name" />
    @if ($badges->isNotEmpty())
        <x-card title="Lencana {{ $name }}">
            <div class="flex flex-wrap gap-2">
                @foreach ($badges as $badge)
                    <span class="inline-flex items-center gap-1 rounded-full bg-kunyit-50 px-3 py-1 text-xs font-semibold text-kunyit-700 dark:bg-hutan-800 dark:text-kunyit">
                        <flux:icon :name="$badge->icon ?? 'trophy'" class="size-4" /> {{ $badge->name }}
                    </span>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
