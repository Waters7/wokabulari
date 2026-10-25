{{-- Kartu rekap rating: rata-rata keseluruhan, per aspek, dan komentar. Nama penilai hanya untuk yang berhak. --}}
@props(['summary', 'aspects', 'title', 'subtitle' => null, 'participants' => null, 'showNames' => false, 'anonymousAware' => false])

<x-card {{ $attributes }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-display text-lg font-bold leading-tight">{{ $title }}</p>
            <p class="text-sm text-hutan/60">
                {{ $summary['count'] }} rating
                @if ($participants)
                    · {{ round($summary['count'] / max($participants, 1) * 100) }}% partisipasi
                @endif
                @if ($subtitle) · {{ $subtitle }} @endif
            </p>
        </div>
        <div class="shrink-0 text-end">
            <p class="font-display text-3xl font-bold text-kunyit-700 dark:text-kunyit">{{ $summary['rating'] !== null ? number_format($summary['rating'], 1, ',', '.') : '–' }}</p>
            <div class="flex justify-end">
                @foreach (range(1, 5) as $n)
                    <flux:icon.star variant="solid" @class(['size-4', 'text-kunyit' => ($summary['rating'] ?? 0) >= $n - 0.5, 'text-zinc-300 dark:text-hutan-800' => ($summary['rating'] ?? 0) < $n - 0.5]) />
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 space-y-2">
        @foreach ($aspects as $key => $label)
            @php($value = $summary['aspects'][$key] ?? null)
            <div class="flex items-center gap-3 text-sm">
                <span class="w-32 shrink-0 truncate">{{ $label }}</span>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800">
                    <div class="h-full rounded-full bg-woka" style="width: {{ $value ? $value / 5 * 100 : 0 }}%"></div>
                </div>
                <span class="w-8 shrink-0 text-end font-semibold">{{ $value !== null ? number_format($value, 1, ',', '.') : '–' }}</span>
            </div>
        @endforeach
    </div>

    @php($comments = $summary['ratings']->filter(fn ($r) => filled($r->comment) || filled($r->suggestion)))
    @if ($comments->isNotEmpty())
        <details class="mt-4 rounded-xl bg-santan p-3 dark:bg-hutan-800" @if ($comments->count() <= 3) open @endif>
            <summary class="cursor-pointer text-sm font-semibold">Komentar & saran ({{ $comments->count() }})</summary>
            <ul class="mt-2 space-y-3">
                @foreach ($comments as $rating)
                    <li class="text-sm" wire:key="c-{{ $rating->getTable() }}-{{ $rating->id }}">
                        <p class="text-xs text-hutan/60">
                            @if ($anonymousAware)
                                {{ $rating->raterNameFor(auth()->user()) }}
                            @elseif ($showNames)
                                {{ $rating->user?->name }}
                            @else
                                Peserta
                            @endif
                            · {{ $rating->rating }}/5 · {{ tanggal($rating->created_at, 'j M') }}
                        </p>
                        @if ($rating->comment)<p>{{ $rating->comment }}</p>@endif
                        @if ($rating->suggestion)<p class="text-hutan/70"><span class="font-semibold">Saran:</span> {{ $rating->suggestion }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-card>
