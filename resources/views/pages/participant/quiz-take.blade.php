<?php

use App\Actions\Quizzes\QuizAttemptService;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Mengerjakan quiz')] class extends Component {
    use WithFileUploads;

    public Quiz $quiz;

    #[Locked]
    public ?int $attemptId = null;

    /** Jawaban peserta sendiri: question_id => nilai. Tidak ada kunci jawaban di sini. */
    public array $answers = [];

    /** Rekaman soal berbicara: question_id => berkas. */
    public array $audios = [];

    public ?string $error = null;

    public function mount(Quiz $quiz, QuizAttemptService $service): void
    {
        $this->authorize('take', $quiz);
        $this->quiz = $quiz;

        try {
            $attempt = $service->start($quiz, auth()->user(), session()->getId(), request()->ip(), request()->userAgent());
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->attemptId = $attempt->id;

        foreach ($attempt->answers as $answer) {
            $value = $answer->answer ?? [];
            $this->answers[$answer->question_id] = $value['option'] ?? $value['text'] ?? $value['pairs'] ?? (isset($value['recorded']) ? 'recorded' : null);
        }
    }

    protected function attempt(): QuizAttempt
    {
        return QuizAttempt::query()->where('user_id', auth()->id())->findOrFail($this->attemptId);
    }

    /**
     * Soal yang aman dikirim ke browser: tanpa is_correct, tanpa pasangan jawaban.
     *
     * @return list<array<string, mixed>>
     */
    protected function questions(QuizAttempt $attempt): array
    {
        $questions = $this->quiz->questions()->with('options')->get()->keyBy('id');

        return collect($attempt->question_order ?? [])->map(function ($id) use ($questions, $attempt) {
            /** @var Question $q */
            $q = $questions->get($id);
            if (! $q) {
                return null;
            }

            $order = collect($attempt->option_order[$id] ?? $q->options->pluck('id')->all());
            $options = $order->map(fn ($optionId) => $q->options->firstWhere('id', $optionId))->filter();

            $item = [
                'id' => $q->id,
                'type' => $q->type,
                'body' => $q->body,
                'reading' => $q->reading,
                'points' => $this->quiz->pointsFor($q),
                'media' => $q->media_path ? $q->mediaUrl() : null,
                'media_is_image' => $q->media_path && preg_match('/\.(jpe?g|png|webp)$/i', $q->media_path),
            ];

            if ($q->type->usesChoice()) {
                $item['options'] = $options->map(fn ($o) => ['id' => $o->id, 'body' => $q->type === QuestionType::Tone ? $o->reading : $o->body, 'reading' => $q->type === QuestionType::Tone ? null : $o->reading])->values()->all();
            }

            if ($q->type === QuestionType::Matching) {
                $item['left'] = $q->options->map(fn ($o) => ['id' => $o->id, 'body' => $o->body, 'reading' => $o->reading])->values()->all();
                // Pilihan kanan diacak (urutan dari attempt) — pasangan benarnya tidak dikirim.
                $item['right'] = $options->pluck('match_key')->values()->all();
            }

            return $item;
        })->filter()->values()->all();
    }

    /** Autosave tiap 10 detik dari browser. */
    public function autosave(QuizAttemptService $service): array
    {
        $this->skipRender();

        try {
            $service->saveAnswers($this->attempt(), $this->normalizedAnswers(), session()->getId());

            return ['ok' => true, 'saved_at' => now()->format('H.i.s')];
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Jawaban dari form diubah ke format layanan: pilihan → option, menjodohkan → pairs, teks → text. */
    protected function normalizedAnswers(): array
    {
        $questions = $this->quiz->questions()->get()->keyBy('id');

        return collect($this->answers)->mapWithKeys(function ($value, $questionId) use ($questions) {
            $type = $questions->get($questionId)?->type;

            return [$questionId => match (true) {
                $type === null, $type === QuestionType::Speaking => null,
                $type->usesChoice() => ['option' => (int) $value],
                $type === QuestionType::Matching => ['pairs' => (array) $value],
                default => ['text' => (string) $value],
            }];
        })->filter()->all();
    }

    public function updatedAudios($file, $questionId): void
    {
        $question = $this->quiz->questions()->findOrFail((int) $questionId);

        try {
            app(QuizAttemptService::class)->saveAudio($this->attempt(), $question, $file, session()->getId());
            $this->answers[(int) $questionId] = 'recorded';
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function tabSwitched(QuizAttemptService $service): int
    {
        $this->skipRender();

        return $service->recordTabSwitch($this->attempt(), session()->getId());
    }

    public function submit(QuizAttemptService $service, bool $auto = false): void
    {
        $attempt = $this->attempt();

        if ($attempt->isInProgress()) {
            try {
                $service->saveAnswers($attempt, $this->normalizedAnswers(), session()->getId());
            } catch (\InvalidArgumentException $e) {
                // Waktu habis: jawaban terakhir yang sempat tersimpan tetap dinilai.
                if (! $auto && $attempt->session_id !== session()->getId()) {
                    $this->error = $e->getMessage();

                    return;
                }
            }

            $service->submit($attempt->fresh(), auto: $auto);
        }

        $this->redirectRoute('quizzes.result', $attempt, navigate: true);
    }

    public function with(): array
    {
        if ($this->error || ! $this->attemptId) {
            return ['questions' => [], 'expiresAt' => null];
        }

        $attempt = $this->attempt();

        if (! $attempt->isInProgress()) {
            $this->redirectRoute('quizzes.result', $attempt, navigate: true);

            return ['questions' => [], 'expiresAt' => null];
        }

        return [
            'questions' => $this->questions($attempt),
            'expiresAt' => $attempt->expires_at->toIso8601String(),
            'display' => $this->quiz->reading_display,
            'isCjk' => $this->quiz->classroom->language->isCjk(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    @if ($error)
        <x-page-header :title="$quiz->title" :back="route('quizzes.index')" />
        <flux:callout icon="exclamation-triangle" color="red" :text="$error" />
    @elseif ($questions)
        @if ($isCjk)<x-cjk-fonts />@endif

        <div x-data="{
                current: 0,
                total: {{ count($questions) }},
                saving: false,
                lastSaved: null,
                warning: null,
                interval: null,
                init() {
                    this.interval = setInterval(() => this.save(), 10000);
                    document.addEventListener('visibilitychange', this.onVisibility = () => {
                        if (document.hidden) {
                            $wire.tabSwitched().then(count => this.warning = 'Tercatat meninggalkan halaman quiz ' + count + '×. Tetap di halaman ini, neh.');
                        }
                    });
                },
                destroy() {
                    clearInterval(this.interval);
                    document.removeEventListener('visibilitychange', this.onVisibility);
                },
                async save() {
                    this.saving = true;
                    const result = await $wire.autosave();
                    this.saving = false;
                    if (result.ok) { this.lastSaved = result.saved_at; } else { this.warning = result.message; }
                },
                answered(id) {
                    const v = $wire.answers[id];
                    return v !== undefined && v !== null && v !== '' && !(typeof v === 'object' && Object.keys(v).length === 0);
                },
             }" x-on:quiz-timeout.window="$wire.submit(true)">

            {{-- Bilah atas: judul, timer, status simpan --}}
            <div class="sticky top-16 z-20 -mx-4 mb-4 border-b border-woka-100 bg-santan/95 px-4 py-2 backdrop-blur lg:top-0 dark:border-hutan-800 dark:bg-hutan-950/95">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ $quiz->title }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">
                            Soal <span x-text="current + 1"></span>/{{ count($questions) }} ·
                            <span x-show="saving">menyimpan…</span>
                            <span x-show="!saving && lastSaved" x-text="'tersimpan ' + lastSaved"></span>
                        </p>
                    </div>
                    <div x-data="countdown('{{ $expiresAt }}', 'quiz-timeout')" class="rounded-full px-3 py-1 font-mono text-lg font-bold"
                         :class="remaining <= 60 ? 'bg-rica text-white animate-pulse' : 'bg-hutan text-santan'" x-text="display" aria-label="Sisa waktu"></div>
                </div>
                <p x-show="warning" x-text="warning" x-cloak class="mt-1 text-xs font-semibold text-rica"></p>
            </div>

            @foreach ($questions as $index => $q)
                <section x-show="current === {{ $index }}" @if ($index > 0) x-cloak @endif wire:key="tq-{{ $q['id'] }}" aria-label="Soal {{ $index + 1 }}">
                    <x-card>
                        <div class="mb-2 flex items-center justify-between text-xs text-hutan/60 dark:text-santan/60">
                            <x-status-badge :status="$q['type']" />
                            <span>{{ angka($q['points'], 1) }} poin</span>
                        </div>

                        <x-reading-text :text="$q['body']" :reading="$q['reading']" :display="$display" as="div" class="whitespace-pre-line text-lg font-semibold leading-relaxed" />

                        @if ($q['media'])
                            @if ($q['media_is_image'])
                                <img src="{{ $q['media'] }}" alt="" class="mt-3 max-h-72 rounded-xl">
                            @else
                                <audio controls preload="auto" class="mt-3 w-full" src="{{ $q['media'] }}" controlslist="nodownload"></audio>
                            @endif
                        @endif

                        <div class="mt-4">
                            @if (isset($q['options']))
                                <div class="space-y-2" role="radiogroup">
                                    @foreach ($q['options'] as $oi => $option)
                                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border-2 px-3 py-2 transition has-[:checked]:border-woka has-[:checked]:bg-woka-50 border-woka-100 dark:border-hutan-800 dark:has-[:checked]:bg-hutan-800">
                                            <input type="radio" wire:model="answers.{{ $q['id'] }}" value="{{ $option['id'] }}" class="size-5 accent-woka">
                                            <span class="font-semibold text-hutan/50 dark:text-santan/50">{{ chr(65 + $oi) }}.</span>
                                            <x-reading-text :text="$option['body']" :reading="$option['reading']" :display="$display" />
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($q['type'] === QuestionType::Matching)
                                <div class="space-y-2">
                                    @foreach ($q['left'] as $left)
                                        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-center gap-2">
                                            <x-reading-text :text="$left['body']" :reading="$left['reading']" :display="$display" class="font-semibold" />
                                            <select wire:model="answers.{{ $q['id'] }}.{{ $left['id'] }}" class="h-12 w-full rounded-xl border border-woka-100 bg-white px-2 dark:border-hutan-800 dark:bg-hutan-900">
                                                <option value="">– pilih –</option>
                                                @foreach ($q['right'] as $right)
                                                    <option value="{{ $right }}">{{ $right }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($q['type'] === QuestionType::Speaking)
                                <x-audio-recorder model="audios.{{ $q['id'] }}" label="Rekam jawabanmu" :max-seconds="180" />
                                <p x-show="$wire.answers[{{ $q['id'] }}] === 'recorded'" class="mt-2 text-sm font-semibold text-woka">✓ Rekaman tersimpan</p>
                            @elseif ($q['type'] === QuestionType::Essay)
                                <flux:textarea wire:model="answers.{{ $q['id'] }}" rows="6" placeholder="Tulis jawabanmu…" />
                            @else
                                <flux:input wire:model="answers.{{ $q['id'] }}" placeholder="Ketik jawaban singkat…" autocomplete="off" autocapitalize="none" />
                            @endif
                        </div>
                    </x-card>
                </section>
            @endforeach

            {{-- Navigasi --}}
            <div class="mt-4 flex items-center justify-between gap-2">
                <flux:button icon="chevron-left" x-on:click="current = Math.max(0, current - 1); save()" x-bind:disabled="current === 0">Sebelumnya</flux:button>
                <flux:button variant="primary" icon:trailing="chevron-right" x-show="current < total - 1" x-on:click="current++; save()">Berikutnya</flux:button>
            </div>

            <div class="mt-4 flex flex-wrap gap-1.5" aria-label="Daftar soal">
                @foreach ($questions as $index => $q)
                    <button type="button" x-on:click="current = {{ $index }}"
                            class="size-10 rounded-lg text-sm font-bold ring-1"
                            :class="current === {{ $index }} ? 'bg-hutan text-santan ring-hutan' : (answered({{ $q['id'] }}) ? 'bg-woka-50 text-woka ring-woka-200' : 'bg-white text-hutan/60 dark:text-santan/60 ring-woka-100 dark:bg-hutan-900')">{{ $index + 1 }}</button>
                @endforeach
            </div>

            <div class="mt-6">
                <x-confirm-button action="submit" label="Kumpulkan jawaban" icon="paper-airplane" variant="primary" size="base"
                                  title="Kumpulkan sekarang?" message="Setelah dikumpulkan, jawaban tidak bisa diubah. Pastikan semua soal sudah dijawab." confirmLabel="Kumpulkan" class="w-full" />
            </div>
        </div>
    @endif
</div>
