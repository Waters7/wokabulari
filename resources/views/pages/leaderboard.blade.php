<?php

use App\Models\Batch;
use App\Models\Classroom;
use App\Services\Gamification\LeaderboardService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Peringkat')] class extends Component {
    /** kelas | antarkelas */
    #[Url]
    public string $view = 'kelas';

    /** minggu ini | minggu lalu | total */
    #[Url]
    public string $period = 'week';

    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->isParticipant() || $user->isTeacher() || $user->can('gamification.manage') || $user->can('dashboard.leader'), 403);

        $this->classroomId ??= $this->classrooms()->first()?->id;
    }

    protected function classrooms()
    {
        $user = auth()->user();
        $batch = Batch::current();

        // Peserta hanya melihat kelasnya; pengajar kelas yang diajar; admin/pimpinan semua kelas angkatan aktif.
        return match (true) {
            $user->isParticipant() => $user->classrooms()->where('batch_id', $batch?->id)->get(),
            $user->isTeacher() && ! $user->can('gamification.manage') => Classroom::query()->where('batch_id', $batch?->id)->taughtBy($user)->get(),
            default => Classroom::query()->where('batch_id', $batch?->id)->orderBy('name')->get(),
        };
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    protected function range(): array
    {
        return match ($this->period) {
            'last' => LeaderboardService::week(now()->subWeek()),
            'all' => [null, null],
            default => LeaderboardService::week(),
        };
    }

    public function with(LeaderboardService $leaderboard): array
    {
        [$from, $to] = $this->range();
        $classrooms = $this->classrooms();
        $classroom = $classrooms->firstWhere('id', $this->classroomId) ?? $classrooms->first();

        return [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'rows' => $this->view === 'kelas' && $classroom ? $leaderboard->classroom($classroom, $from, $to) : collect(),
            'classRows' => $this->view === 'antarkelas' ? $leaderboard->classes(Batch::current(), $from, $to) : collect(),
            'best' => $leaderboard->bestOfWeek(),
            'from' => $from,
            'to' => $to,
            'canPrint' => auth()->user()->can('gamification.manage') || auth()->user()->can('dashboard.leader'),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Peringkat" :subtitle="$from ? tanggal($from, 'j M').' – '.tanggal($to) : 'Sepanjang program'" />

    @if ($best)
        <x-card class="mb-4 bg-kunyit-50! ring-kunyit! dark:bg-hutan-900!">
            <div class="flex items-center gap-3">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-kunyit text-hutan">
                    <flux:icon.trophy variant="solid" class="size-7" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-kunyit-700 dark:text-kunyit">Peserta terbaik minggu ini</p>
                    <p class="truncate font-display text-lg font-bold">{{ $best['user']->displayName() }}</p>
                    <p class="text-sm text-hutan/70 dark:text-santan/70">{{ angka($best['points']) }} poin</p>
                </div>
                @if ($canPrint)
                    <flux:button size="sm" icon="printer" :href="route('leaderboard.charter', ['minggu' => $best['from']->toDateString()])" target="_blank">Piagam</flux:button>
                @endif
            </div>
        </x-card>
    @endif

    <div class="mb-3 flex flex-col gap-2 sm:flex-row">
        <flux:radio.group wire:model.live="view" variant="segmented" class="sm:flex-1">
            <flux:radio value="kelas" label="Per kelas" />
            <flux:radio value="antarkelas" label="Antarkelas" />
        </flux:radio.group>
        <flux:select wire:model.live="period" class="sm:max-w-44" aria-label="Periode">
            <flux:select.option value="week">Minggu ini</flux:select.option>
            <flux:select.option value="last">Minggu lalu</flux:select.option>
            <flux:select.option value="all">Total</flux:select.option>
        </flux:select>
    </div>

    @if ($view === 'kelas')
        @if ($classrooms->count() > 1)
            <flux:select wire:model.live="classroomId" class="mb-3" aria-label="Kelas">
                @foreach ($classrooms as $c)
                    <flux:select.option :value="$c->id">{{ $c->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @forelse ($rows as $row)
                @php($me = $row['user']->id === auth()->id())
                <div @class(['flex items-center gap-3 px-4 py-3', 'bg-woka-50 dark:bg-hutan-800' => $me]) wire:key="r-{{ $row['user']->id }}">
                    <span @class(['flex size-9 shrink-0 items-center justify-center rounded-full font-display font-bold',
                        'bg-kunyit text-hutan' => $row['rank'] === 1,
                        'bg-zinc-200 text-hutan dark:bg-zinc-600 dark:text-santan' => $row['rank'] === 2,
                        'bg-orange-200 text-hutan dark:bg-orange-900 dark:text-santan' => $row['rank'] === 3,
                        'bg-santan text-hutan/70 dark:bg-hutan-900 dark:text-santan/70' => $row['rank'] > 3])>{{ $row['rank'] }}</span>
                    <p class="min-w-0 flex-1 truncate font-semibold">{{ $row['user']->displayName() }}@if ($me) <span class="text-xs font-normal text-hutan/60 dark:text-santan/60">(ngana)</span>@endif</p>
                    <span class="shrink-0 font-display text-lg font-bold">{{ angka($row['points']) }}</span>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Belum ada kelas.</p>
            @endforelse
        </x-card>
    @else
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @forelse ($classRows as $row)
                <div class="flex items-center gap-3 px-4 py-3" wire:key="c-{{ $row['classroom']->id }}">
                    <span @class(['flex size-9 shrink-0 items-center justify-center rounded-full font-display font-bold', 'bg-kunyit text-hutan' => $row['rank'] === 1, 'bg-santan text-hutan/70 dark:bg-hutan-900 dark:text-santan/70' => $row['rank'] > 1])>{{ $row['rank'] }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $row['classroom']->name }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $row['members'] }} peserta · total {{ angka($row['total']) }}</p>
                    </div>
                    <div class="shrink-0 text-end">
                        <p class="font-display text-lg font-bold">{{ angka($row['average'], 1) }}</p>
                        <p class="text-[10px] text-hutan/60 dark:text-santan/60">rata-rata</p>
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Belum ada angkatan aktif.</p>
            @endforelse
        </x-card>
    @endif
</div>
