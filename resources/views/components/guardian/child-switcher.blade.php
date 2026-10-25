{{-- Pemilih anak di portal wali (tampil bila wali punya lebih dari satu anak terverifikasi). --}}
@props(['children', 'child'])

@if ($children->count() > 1)
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist" aria-label="Pilih anak">
        @foreach ($children as $item)
            <button type="button" wire:click="selectChild({{ $item->id }})" wire:key="ch-{{ $item->id }}" role="tab" :aria-selected="@js($child?->id === $item->id)"
                    @class(['flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1',
                        'bg-woka text-white ring-woka' => $child?->id === $item->id,
                        'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $child?->id !== $item->id])>
                <flux:avatar size="xs" :name="$item->name" :initials="$item->initials()" />
                {{ \Illuminate\Support\Str::words($item->name, 2, '') }}
            </button>
        @endforeach
    </div>
@endif
