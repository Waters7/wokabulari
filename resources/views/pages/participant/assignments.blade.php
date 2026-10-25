<?php

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Tugas')] class extends Component {
    #[Url]
    public string $tab = 'todo';

    public function with(): array
    {
        $user = auth()->user();

        $assignments = Assignment::query()
            ->forParticipant($user)
            ->with(['classroom', 'submissions' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderBy('due_at')
            ->get()
            ->map(function (Assignment $assignment) {
                $submission = $assignment->submissions->first();
                $assignment->setAttribute('my_status', $submission?->status ?? SubmissionStatus::NotSubmitted);
                $assignment->setAttribute('my_submission', $submission);

                return $assignment;
            });

        $pending = fn ($a) => in_array($a->my_status, [SubmissionStatus::NotSubmitted, SubmissionStatus::RevisionRequested], true);

        $groups = [
            'todo' => $assignments->filter(fn ($a) => $pending($a) && ! $a->isDueSoon()),
            'soon' => $assignments->filter(fn ($a) => $pending($a) && $a->isDueSoon()),
            'done' => $assignments->reject($pending)->sortByDesc('due_at'),
        ];

        return [
            'groups' => $groups,
            'items' => $groups[$this->tab] ?? $groups['todo'],
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Tugas" subtitle="Kumpulkan dari HP: tulis jawaban, foto tugas tulis, atau rekam suara." />

    <div class="mb-4 grid grid-cols-3 gap-1 rounded-xl bg-white p-1 ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800" role="tablist">
        @foreach (['todo' => 'Belum dikerjakan', 'soon' => 'Mendekati tenggat', 'done' => 'Selesai'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['min-h-11 rounded-lg px-1 text-xs font-semibold leading-tight sm:text-sm', 'bg-woka text-white' => $tab === $key, 'text-hutan/70 dark:text-santan/70' => $tab !== $key])>
                {{ $label }}
                @if ($groups[$key]->count())<span class="ms-0.5">({{ $groups[$key]->count() }})</span>@endif
            </button>
        @endforeach
    </div>

    <div class="space-y-2">
        @forelse ($items as $assignment)
            <x-card :href="route('assignments.show', $assignment)" wire:key="pa-{{ $assignment->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $assignment->title }}</p>
                        <p @class(['text-xs', 'font-semibold text-rica' => $assignment->isDueSoon() || ($assignment->isOverdue() && $tab !== 'done'), 'text-hutan/60 dark:text-santan/60' => ! $assignment->isDueSoon()])>
                            Tenggat {{ tanggal_jam($assignment->due_at) }}
                            @if ($assignment->due_at->isFuture()) · {{ $assignment->due_at->diffForHumans(['parts' => 2]) }}@endif
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <x-status-badge :status="$assignment->submission_type" />
                            <x-status-badge :status="$assignment->my_status" />
                        </div>
                    </div>
                    @if ($assignment->my_submission?->final_score !== null)
                        <span class="font-display text-2xl font-bold text-woka">{{ angka($assignment->my_submission->final_score, 1) }}</span>
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state icon="clipboard-document-check" :title="$tab === 'done' ? 'Belum ada tugas selesai' : 'Tidak ada tugas di sini'" :description="$tab === 'done' ? null : 'Mantap, ngana so beres!'" />
        @endforelse
    </div>
</div>
