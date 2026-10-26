<?php

use App\Support\Navigation;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman ringkas berisi pintasan menu (untuk bottom nav peserta: Kelas & Asrama).
 */
new #[Title('Menu')] class extends Component {
    public string $section = 'kelas';

    public function mount(): void
    {
        $this->section = request()->routeIs('participant.dorm') ? 'asrama' : 'kelas';
    }

    public function with(): array
    {
        $groups = collect(app(Navigation::class)->sidebar(auth()->user()))->keyBy('heading');
        $headings = $this->section === 'asrama' ? ['Asrama & Konsumsi'] : ['Kelas Saya', 'Aktivitas'];

        return [
            'title' => $this->section === 'asrama' ? (auth()->user()->livesInDorm() ? 'Asrama & konsumsi' : 'Konsumsi & kesehatan') : 'Kelas saya',
            'items' => collect($headings)->flatMap(fn ($h) => $groups->get($h)['items'] ?? [])->all(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header :title="$title" />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($items as $item)
            <a href="{{ $item['url'] }}" wire:navigate
               class="flex min-h-28 flex-col items-start justify-between rounded-2xl bg-white p-4 ring-1 ring-woka-100 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800">
                <span class="flex size-11 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
                    <flux:icon :name="$item['icon']" class="size-6" />
                </span>
                <span class="mt-3 font-semibold leading-tight">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
