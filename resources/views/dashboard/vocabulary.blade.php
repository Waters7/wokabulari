{{-- Peserta: 5 kartu kosakata hari ini (geser untuk kartu berikutnya, ketuk untuk melihat arti). --}}
@php
    $vocabulary = app(\App\Services\DailyVocabulary::class)->for(auth()->user());
@endphp

@if ($vocabulary)
    @php
        $vocabLanguage = $vocabulary['language'];
        $vocabCards = $vocabulary['words'];
        $cjk = in_array($vocabLanguage->code, ['zh', 'ja'], true);
    @endphp
    @if ($cjk)
        <x-cjk-fonts />
    @endif

    <section data-test="daily-vocabulary" wire:key="vocab-{{ today()->toDateString() }}"
             x-data="{
                 index: 0,
                 flipped: {},
                 count: {{ $vocabCards->count() }},
                 sync(el) { this.index = Math.round(el.scrollLeft / el.firstElementChild.offsetWidth) },
                 go(i) { const el = this.$refs.track; el.scrollTo({ left: i * el.firstElementChild.offsetWidth, behavior: 'smooth' }) },
                 speak(text) {
                     if (! ('speechSynthesis' in window)) return;
                     window.speechSynthesis.cancel();
                     const voice = new SpeechSynthesisUtterance(text);
                     voice.lang = @js(\App\Services\DailyVocabulary::speechLang($vocabLanguage));
                     voice.rate = 0.8;
                     window.speechSynthesis.speak(voice);
                 },
             }">
        <div class="mb-2 flex items-end justify-between gap-2">
            <div class="min-w-0">
                <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Kosakata hari ini · {{ $vocabLanguage->name }}</h2>
                <p class="text-xs text-hutan/50 dark:text-santan/50">Geser untuk kartu berikutnya, ketuk untuk melihat arti. Berganti tiap hari.</p>
            </div>
            <span class="shrink-0 text-xs font-semibold tabular-nums text-hutan/60 dark:text-santan/60"><span x-text="index + 1">1</span>/{{ $vocabCards->count() }}</span>
        </div>

        <div x-ref="track" @scroll.debounce.60ms="sync($el)"
             class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($vocabCards as $i => $word)
                <div class="w-[85%] shrink-0 snap-start sm:w-72" wire:key="vc-{{ $word->id }}">
                    <button type="button" @click="flipped[{{ $i }}] = ! flipped[{{ $i }}]" :aria-pressed="flipped[{{ $i }}] ? 'true' : 'false'"
                            class="relative flex h-44 w-full flex-col items-center justify-center rounded-2xl bg-white px-4 text-center ring-1 ring-zinc-200/70 transition dark:bg-hutan-900 dark:ring-hutan-800"
                            :class="flipped[{{ $i }}] ? 'ring-woka-200 bg-woka-50/60 dark:bg-hutan-800' : ''">
                        <span class="absolute start-3 top-3 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-hutan/60 dark:bg-hutan-800 dark:text-santan/60">{{ $word->topic ?? 'Kosakata' }}</span>
                        <span x-show="! flipped[{{ $i }}]" class="flex flex-col items-center">
                            <span @class(['text-4xl font-bold leading-tight text-hutan dark:text-santan', 'font-cjk' => $cjk])>{{ $word->word }}</span>
                            @if ($word->reading)
                                <span class="mt-1 text-sm text-woka dark:text-woka-light">{{ $word->reading }}</span>
                            @endif
                            <span class="mt-3 text-[11px] text-hutan/40 dark:text-santan/40">Ketuk untuk arti</span>
                        </span>
                        <span x-show="flipped[{{ $i }}]" x-cloak class="flex flex-col items-center">
                            <span class="font-display text-2xl font-bold leading-tight">{{ $word->meaning }}</span>
                            <span @class(['mt-1 text-sm text-hutan/60 dark:text-santan/60', 'font-cjk' => $cjk])>{{ $word->word }}@if ($word->reading) · {{ $word->reading }}@endif</span>
                            @if ($word->example)
                                <span @class(['mt-2 text-xs', 'font-cjk' => $cjk])>{{ $word->example }}</span>
                                @if ($word->example_meaning)<span class="text-xs text-hutan/50 dark:text-santan/50">{{ $word->example_meaning }}</span>@endif
                            @endif
                        </span>
                    </button>
                    <div class="mt-1 flex justify-center">
                        <button type="button" @click="speak(@js($word->word))" class="inline-flex min-h-9 items-center gap-1 rounded-full px-3 text-xs font-semibold text-woka hover:bg-woka-50 dark:text-woka-light dark:hover:bg-hutan-800">
                            <flux:icon.speaker-wave class="size-4" /> Dengar
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-center gap-1.5" aria-hidden="true">
            @foreach ($vocabCards as $i => $word)
                <button type="button" tabindex="-1" @click="go({{ $i }})" class="h-1.5 rounded-full transition-all"
                        :class="index === {{ $i }} ? 'w-5 bg-woka' : 'w-1.5 bg-zinc-300 dark:bg-hutan-700'"></button>
            @endforeach
        </div>
    </section>
@endif
