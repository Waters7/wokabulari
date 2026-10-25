<?php

use App\Services\MealRoster;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alergi & pantangan')] class extends Component {
    public function with(MealRoster $roster): array
    {
        $participants = $roster->participants()->filter(fn ($p) => $p->profile?->hasAllergies())->values();
        $grouped = $participants->groupBy(fn ($p) => $p->currentRoomAssignment?->room?->dormitory?->name ?? 'Belum ada asrama');

        // Teks ringkas untuk diteruskan ke vendor (WhatsApp).
        $text = "Daftar alergi/pantangan peserta SMK Go Global per ".tanggal(today()).":\n"
            .$participants->values()->map(fn ($p, $i) => ($i + 1).'. '.$p->name.' — '.$p->profile->allergies)->implode("\n");

        return [
            'grouped' => $grouped,
            'count' => $participants->count(),
            'total' => $roster->participants()->count(),
            'text' => $text,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Alergi & pantangan" subtitle="{{ $count }} dari {{ $total }} peserta punya catatan alergi atau pantangan makanan.">
        @if ($count)
            <x-slot:actions>
                <div x-data="{ copied: false }">
                    <flux:button icon="clipboard-document" x-on:click="navigator.clipboard.writeText(@js($text)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="!copied">Salin untuk vendor</span><span x-show="copied" x-cloak>Tersalin</span>
                    </flux:button>
                </div>
            </x-slot:actions>
        @endif
    </x-page-header>

    @forelse ($grouped as $dormitory => $participants)
        <h2 class="mb-2 mt-4 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">{{ $dormitory }}</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($participants as $participant)
                <div class="flex items-start gap-3 px-4 py-3" wire:key="a-{{ $participant->id }}">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-kunyit/20 text-kunyit-700">
                        <flux:icon.exclamation-triangle class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $participant->name }}</p>
                        <p class="text-sm">{{ $participant->profile->allergies }}</p>
                        @if ($room = $participant->currentRoomAssignment?->room)
                            <p class="text-xs text-hutan/60 dark:text-santan/60">Kamar {{ $room->number }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </x-card>
    @empty
        <x-empty-state icon="check-circle" title="Tidak ada catatan alergi" description="Belum ada peserta yang mencatat alergi atau pantangan makanan." />
    @endforelse
</div>
