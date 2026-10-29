<?php

use App\Models\ProgramEvaluation;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Survei')] class extends Component {
    public function with(): array
    {
        $user = auth()->user();
        $evaluations = ProgramEvaluation::query()->for($user)
            ->where(fn ($q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->orderBy('closes_at')
            ->get();
        $doneIds = $evaluations->filter(fn (ProgramEvaluation $e) => $e->hasResponded($user))->pluck('id');

        return [
            'pending' => $evaluations->filter(fn ($e) => $e->isOpen() && ! $doneIds->contains($e->id))->values(),
            'done' => $evaluations->whereIn('id', $doneIds)->values(),
            'missed' => $evaluations->filter(fn ($e) => $e->isClosed() && ! $doneIds->contains($e->id))->values(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Survei" subtitle="Masukan ngana membantu tim kerja memperbaiki fasilitas dan program." />

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Perlu diisi</h2>
    <div class="mb-6 space-y-2">
        @forelse ($pending as $evaluation)
            <x-card :href="route('evaluations.fill', $evaluation)" wire:key="pe-{{ $evaluation->id }}" class="ring-2 ring-kunyit">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
                        <flux:icon :name="$evaluation->category->icon()" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold leading-tight">{{ $evaluation->title }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">
                            Tenggat {{ tanggal_jam($evaluation->closes_at) }} · {{ $evaluation->closes_at->diffForHumans() }}
                            @if ($evaluation->is_anonymous) · anonim @endif
                        </p>
                    </div>
                    <flux:icon.chevron-right class="size-5 shrink-0 text-hutan/40" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="check-badge" title="Tidak ada survei yang menunggu" description="Survei baru akan muncul di sini dan di notifikasi." />
        @endforelse
    </div>

    @if ($done->isNotEmpty() || $missed->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Riwayat</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($done as $evaluation)
                <div class="flex items-center justify-between gap-2 px-4 py-3 text-sm" wire:key="de-{{ $evaluation->id }}">
                    <span class="min-w-0 truncate">{{ $evaluation->title }}</span>
                    <flux:badge size="sm" color="green" icon="check">Sudah diisi</flux:badge>
                </div>
            @endforeach
            @foreach ($missed as $evaluation)
                <div class="flex items-center justify-between gap-2 px-4 py-3 text-sm" wire:key="me-{{ $evaluation->id }}">
                    <span class="min-w-0 truncate">{{ $evaluation->title }}</span>
                    <flux:badge size="sm" color="zinc">Ditutup</flux:badge>
                </div>
            @endforeach
        </x-card>
    @endif
</div>
