<?php

use App\Enums\AttemptStatus;
use App\Models\Quiz;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quiz & ujian')] class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        $quizzes = Quiz::query()->visibleTo($user)
            ->withCount('questions')
            ->with(['attempts' => fn ($q) => $q->where('user_id', $user->id)->latest('id')])
            ->orderBy('opens_at')
            ->get();

        $groups = ['active' => collect(), 'upcoming' => collect(), 'done' => collect()];

        foreach ($quizzes as $quiz) {
            $inProgress = $quiz->attempts->firstWhere('status', AttemptStatus::InProgress);
            $left = $quiz->max_attempts - $quiz->attempts->count();

            if ($inProgress || ($quiz->isOpen() && $left > 0)) {
                $groups['active']->push($quiz);
            } elseif ($quiz->opens_at?->isFuture() && ! $quiz->isClosed()) {
                $groups['upcoming']->push($quiz);
            } else {
                $groups['done']->push($quiz);
            }
        }

        return ['groups' => $groups];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Quiz & ujian" subtitle="Quiz, pretest, posttest, dan tryout kelasmu." />

    @foreach (['active' => 'Bisa dikerjakan', 'upcoming' => 'Akan datang', 'done' => 'Selesai'] as $key => $label)
        @continue($groups[$key]->isEmpty())
        <h2 class="mb-2 mt-5 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60 first:mt-0">{{ $label }}</h2>
        <div class="space-y-2">
            @foreach ($groups[$key] as $quiz)
                @php($last = $quiz->attempts->first())
                <x-card wire:key="pq-{{ $quiz->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap gap-1"><x-status-badge :status="$quiz->type" /></div>
                            <p class="mt-1 font-semibold">{{ $quiz->title }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">
                                {{ $quiz->questions_count }} soal · {{ $quiz->duration_minutes }} menit
                                @if ($quiz->closes_at) · tutup {{ tanggal_jam($quiz->closes_at) }} @endif
                                @if ($key === 'upcoming') · dibuka {{ tanggal_jam($quiz->opens_at) }} @endif
                            </p>
                        </div>
                        <div class="shrink-0 text-end">
                            @if ($key === 'active')
                                <flux:button size="sm" variant="primary" icon="play" :href="route('quizzes.take', $quiz)" wire:navigate>
                                    {{ $last?->status === AttemptStatus::InProgress ? 'Lanjutkan' : 'Mulai' }}
                                </flux:button>
                            @elseif ($key === 'upcoming')
                                <flux:badge size="sm" color="sky" icon="clock">Segera</flux:badge>
                            @elseif ($last)
                                @if ($quiz->resultsVisible() && $last->percentage !== null)
                                    <a href="{{ route('quizzes.result', $last) }}" wire:navigate class="font-display text-2xl font-bold text-woka">{{ angka($last->percentage, 0) }}</a>
                                @else
                                    <a href="{{ route('quizzes.result', $last) }}" wire:navigate><x-status-badge :status="$last->status" /></a>
                                @endif
                            @else
                                <flux:badge size="sm" color="zinc">Tidak dikerjakan</flux:badge>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endforeach

    @if (collect($groups)->every->isEmpty())
        <x-empty-state icon="pencil-square" title="Belum ada quiz" />
    @endif
</div>
