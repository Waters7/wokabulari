<?php

use App\Actions\Classrooms\AssignParticipants;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail kelas')] class extends Component {
    public Classroom $classroom;

    public string $search = '';

    /** @var list<int> */
    public array $selected = [];

    public function mount(Classroom $classroom): void
    {
        $this->authorize('view', $classroom);
        $this->classroom = $classroom->load(['batch', 'language', 'teacher', 'teachers', 'location']);
    }

    public function addSelected(AssignParticipants $assign): void
    {
        $this->authorize('update', $this->classroom);

        if ($this->selected === []) {
            return;
        }

        $count = $assign->handle($this->classroom, $this->selected, auth()->user());

        Flux::toast(variant: 'success', text: $count.' peserta ditambahkan ke kelas.');
        $this->reset('selected', 'search');
    }

    public function remove(int $userId): void
    {
        $this->authorize('update', $this->classroom);
        $this->classroom->participants()->detach($userId);
        Flux::toast(text: 'Peserta dikeluarkan dari kelas.');
    }

    public function with(): array
    {
        return [
            'participants' => $this->classroom->participants()->with('profile')->orderBy('name')->get(),
            'candidates' => blank($this->search) ? collect() : User::query()
                ->participants()
                ->search($this->search)
                ->whereDoesntHave('classrooms', fn ($query) => $query->where('classrooms.id', $this->classroom->id))
                ->with('classrooms')
                ->limit(20)
                ->get(),
        ];
    }
}; ?>

<div>
    <x-page-header :title="$classroom->label()" :back="route('master.classrooms')"
                   :subtitle="$classroom->language->name.' · '.$classroom->batch->name.' · Pengajar: '.($classroom->teachers->sortByDesc(fn ($t) => $t->id === $classroom->teacher_id)->pluck('name')->join(', ') ?: '–')" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2" title="Peserta ({{ $participants->count() }})">
            @if ($participants->isEmpty())
                <x-empty-state icon="user-group" title="Belum ada peserta" description="Cari dan tambahkan peserta di panel samping." />
            @else
                <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
                    @foreach ($participants as $participant)
                        <li class="flex items-center gap-3 py-2" wire:key="p-{{ $participant->id }}">
                            <flux:avatar size="sm" :name="$participant->name" :initials="$participant->initials()" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('master.participants.show', $participant) }}" wire:navigate class="block truncate font-semibold hover:text-woka">{{ $participant->name }}</a>
                                <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $participant->profile?->school_name ?? '–' }}</p>
                            </div>
                            <x-confirm-button action="remove({{ $participant->id }})" icon="user-minus" title="Keluarkan {{ $participant->name }} dari kelas?" message="Data kehadiran dan nilai tetap tersimpan." confirmLabel="Keluarkan" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Tambah peserta">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Nama, NIS, atau email…" />
            <div class="mt-3 space-y-1">
                @foreach ($candidates as $candidate)
                    <label class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-woka-50 dark:hover:bg-hutan-800" wire:key="c-{{ $candidate->id }}">
                        <input type="checkbox" wire:model="selected" value="{{ $candidate->id }}" class="size-5 rounded accent-woka">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ $candidate->name }}</span>
                            <span class="block truncate text-xs text-hutan/60 dark:text-santan/60">{{ $candidate->classrooms->pluck('name')->join(', ') ?: 'Belum punya kelas' }}</span>
                        </span>
                    </label>
                @endforeach
                @if (filled($search) && $candidates->isEmpty())
                    <flux:text>Tidak ada peserta yang cocok.</flux:text>
                @endif
            </div>
            @if ($candidates->isNotEmpty())
                <flux:button class="mt-3 w-full" variant="primary" icon="user-plus" wire:click="addSelected">Tambahkan ke kelas</flux:button>
            @endif
        </x-card>
    </div>
</div>
