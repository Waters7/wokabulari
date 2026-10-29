<?php

use App\Support\UserGuide;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Panduan')] class extends Component {
    #[Url(as: 'untuk')]
    public string $guide = '';

    public function mount(UserGuide $guides): void
    {
        $keys = $guides->keysFor(auth()->user());

        if (! in_array($this->guide, $keys, true)) {
            $this->guide = $keys[0] ?? '';
        }
    }

    public function with(UserGuide $guides): array
    {
        $keys = $guides->keysFor(auth()->user());
        // Pilihan panduan dari URL tetap dibatasi pada role pengguna.
        $current = in_array($this->guide, $keys, true) ? $guides->get($this->guide) : null;

        return [
            'options' => collect($keys)->mapWithKeys(fn (string $key) => [$key => $guides->get($key)]),
            'current' => $current,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Panduan penggunaan" subtitle="Langkah singkat memakai Wokabulari sesuai peran Anda." />

    @if ($options->count() > 1)
        <div class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist">
            @foreach ($options as $key => $option)
                <button type="button" wire:click="$set('guide', '{{ $key }}')" wire:key="g-{{ $key }}" role="tab" aria-selected="{{ $guide === $key ? 'true' : 'false' }}"
                        @class([
                            'flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold ring-1 transition',
                            'bg-woka text-white ring-woka' => $guide === $key,
                            'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $guide !== $key,
                        ])>
                    <flux:icon :name="$option['icon']" class="size-4" /> {{ $option['title'] }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($current)
        <p class="mb-4 text-sm text-hutan/70 dark:text-santan/70">{{ $current['intro'] }}</p>

        <div class="space-y-2" data-test="guide-{{ $guide }}">
            @foreach ($current['topics'] as $i => $topic)
                <details @if ($i === 0) open @endif wire:key="t-{{ $guide }}-{{ $i }}"
                         class="group rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 px-4 py-3 font-semibold">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-woka-50 text-sm text-woka dark:bg-hutan-800 dark:text-woka-light">{{ $i + 1 }}</span>
                        <span class="min-w-0 flex-1">{{ $topic['title'] }}</span>
                        <flux:icon.chevron-down class="size-4 shrink-0 text-hutan/40 transition group-open:rotate-180" />
                    </summary>
                    <ol class="list-decimal space-y-2 px-4 pb-4 ps-12 text-sm leading-relaxed">
                        @foreach ($topic['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-hutan/50 dark:text-santan/50">Masih bingung? Hubungi admin program atau tim kerja di lokasi.</p>
    @else
        <x-empty-state icon="book-open" title="Belum ada panduan untuk akun ini" description="Hubungi admin program bila akun Anda belum memiliki peran." />
    @endif
</div>
