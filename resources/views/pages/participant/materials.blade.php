<?php

use App\Models\MaterialAccessLog;
use App\Models\SessionMaterial;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Materi')] class extends Component {
    #[Url]
    public string $search = '';

    public function with(): array
    {
        $user = auth()->user();
        $classroomIds = $user->classrooms()->pluck('classrooms.id');

        $materials = SessionMaterial::query()
            ->with('session')
            ->whereHas('session', fn ($q) => $q->whereIn('classroom_id', $classroomIds))
            ->visibleToParticipants()
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$this->search}%")->orWhereHas('session', fn ($s) => $s->where('topic', 'like', "%{$this->search}%"))))
            ->get()
            ->sortByDesc(fn ($m) => $m->session->date->timestamp)
            ->groupBy('learning_session_id');

        return [
            'groups' => $materials,
            'opened' => MaterialAccessLog::query()->where('user_id', $user->id)->pluck('session_material_id')->flip(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Materi" subtitle="Materi per sesi. Materi yang pernah dibuka tersimpan untuk dibaca saat luring." />

    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari materi atau topik…" class="mb-4" />

    <div class="space-y-5">
        @forelse ($groups as $materials)
            @php($session = $materials->first()->session)
            <section wire:key="g-{{ $session->id }}">
                <h2 class="mb-2 text-sm font-bold text-hutan/70 dark:text-santan/70">{{ tanggal($session->date, 'l, j M') }} · {{ $session->topic }}</h2>
                <div class="space-y-2">
                    @foreach ($materials as $material)
                        <x-card :href="route('materials.show', $material)" wire:key="pm-{{ $material->id }}" class="flex items-center gap-3">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
                                <flux:icon :name="$material->type->icon()" class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $material->title }}</p>
                                <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $material->type->label() }} {{ $material->sizeLabel() ? '· '.$material->sizeLabel() : '' }}</p>
                            </div>
                            @unless ($opened->has($material->id))
                                <flux:badge size="sm" color="amber">Baru</flux:badge>
                            @endunless
                            <flux:icon.chevron-right class="size-5 text-hutan/40" />
                        </x-card>
                    @endforeach
                </div>
            </section>
        @empty
            <x-empty-state icon="book-open" title="Belum ada materi" description="Materi dari pengajar akan muncul di sini." />
        @endforelse
    </div>
</div>
