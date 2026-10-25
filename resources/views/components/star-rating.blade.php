{{-- Input rating 1–5 (ikon bintang/hati) yang terhubung ke properti Livewire. --}}
@props(['model', 'label' => null, 'icon' => 'star', 'size' => 'size-9'])

<div x-data="{ value: $wire.$entangle(@js($model)), hover: 0 }" {{ $attributes->class('flex flex-col gap-1') }}>
    @if ($label)
        <span class="text-sm font-semibold">{{ $label }}</span>
    @endif
    <div class="flex items-center gap-1" role="radiogroup" aria-label="{{ $label ?? 'Rating' }}" x-on:mouseleave="hover = 0">
        @foreach (range(1, 5) as $n)
            <button type="button" role="radio" :aria-checked="value == {{ $n }}" aria-label="{{ $n }} dari 5"
                    x-on:click="value = (value == {{ $n }} ? null : {{ $n }})" x-on:mouseenter="hover = {{ $n }}"
                    class="flex min-h-11 min-w-11 items-center justify-center rounded-lg transition active:scale-90">
                <span :class="(hover || value) >= {{ $n }} ? '{{ $icon === 'heart' ? 'text-rica' : 'text-kunyit' }}' : 'text-zinc-300 dark:text-hutan-800'">
                    @if ($icon === 'heart')
                        <flux:icon.heart variant="solid" class="{{ $size }}" />
                    @else
                        <flux:icon.star variant="solid" class="{{ $size }}" />
                    @endif
                </span>
            </button>
        @endforeach
        <span class="ms-1 text-sm font-semibold text-hutan/60 dark:text-santan/60" x-text="value ? value + '/5' : ''"></span>
    </div>
</div>
