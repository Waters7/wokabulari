<?php

use App\Actions\Quizzes\GradeQuizAnswer;
use App\Enums\AttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Services\EssayKeywordScorer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Perlu dinilai')] class extends Component {
    use WithPagination;

    /** Peserta per halaman pada tampilan per peserta. */
    public const PER_PAGE = 10;

    /** 'soal' = semua jawaban satu soal (bawaan), 'peserta' = semua jawaban satu peserta. */
    #[Url(as: 'tampilan')]
    public string $view = 'soal';

    #[Url]
    public ?int $quiz = null;

    #[Url(as: 'soal')]
    public ?int $questionId = null;

    #[Url(as: 'cari')]
    public string $search = '';

    /** @var array<int, array{score: mixed, feedback: mixed}> */
    public array $grades = [];

    /** @var list<string> Jawaban yang dicentang untuk nilai massal */
    public array $selected = [];

    public string $bulkScore = '';

    public string $keywords = '';

    public string $keywordsNeeded = '1';

    public function mount(): void
    {
        $this->view = in_array($this->view, ['soal', 'peserta'], true) ? $this->view : 'soal';
        $this->ensureQuiz();
    }

    public function updatedView(): void
    {
        $this->resetPage();
        $this->ensureQuiz();
    }

    public function updatingQuiz(): void
    {
        $this->resetPage();
    }

    public function updatedQuiz(): void
    {
        $this->questionId = null;
        $this->selected = [];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Tampilan per soal butuh satu quiz: pilih quiz dengan antrean terbanyak bila belum dipilih. */
    protected function ensureQuiz(): void
    {
        if ($this->view === 'soal' && ! $this->quiz) {
            $this->quiz = $this->gradableQuizzes()->withCount(['attempts' => fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)])
                ->orderByDesc('attempts_count')->value('id');
        }
    }

    /** Quiz yang boleh dinilai pengguna ini. */
    protected function scopeQuizzes(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when(! $user->isSuperAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('teacher_id', $user->id)->orWhereHas('classroom', fn ($c) => $c->taughtBy($user))));
    }

    protected function gradableQuizzes(): Builder
    {
        return $this->scopeQuizzes(Quiz::query())->whereHas('attempts', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading));
    }

    /** Attempt yang masih punya jawaban esai/berbicara belum dinilai. */
    protected function pendingAttempts(): Builder
    {
        return QuizAttempt::query()
            ->where('status', AttemptStatus::NeedsGrading)
            ->whereHas('answers', fn ($q) => $q->whereNull('score'))
            ->whereHas('quiz', fn ($q) => $this->scopeQuizzes($q)->when($this->quiz, fn ($q) => $q->whereKey($this->quiz)))
            ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.trim($this->search).'%')));
    }

    /** Quiz terpilih (sudah dicek hak aksesnya). */
    protected function currentQuiz(): ?Quiz
    {
        return $this->quiz ? $this->scopeQuizzes(Quiz::query())->with(['classroom', 'questions'])->find($this->quiz) : null;
    }

    /** @return Collection<int, int> id soal => jumlah jawaban yang menunggu */
    protected function pendingPerQuestion(Quiz $quiz): Collection
    {
        return QuizAnswer::query()->whereNull('score')
            ->whereHas('attempt', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)->where('quiz_id', $quiz->id))
            ->selectRaw('question_id, count(*) as aggregate')->groupBy('question_id')->pluck('aggregate', 'question_id');
    }

    /** Soal aktif: pilihan pengajar, atau soal pertama yang masih ada antreannya. */
    protected function currentQuestionId(Quiz $quiz, Collection $pending): ?int
    {
        if ($this->questionId && $pending->has($this->questionId)) {
            return $this->questionId;
        }

        return $quiz->questions->first(fn ($q) => $pending->has($q->id))?->id;
    }

    /** Jawaban yang menunggu untuk satu soal. */
    protected function questionAnswers(Quiz $quiz, int $questionId): Collection
    {
        return QuizAnswer::query()->whereNull('score')->where('question_id', $questionId)
            ->whereHas('attempt', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)->where('quiz_id', $quiz->id)
                ->when(trim($this->search) !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.trim($this->search).'%'))))
            ->with('attempt.user')->get()
            ->sortBy(fn (QuizAnswer $a) => mb_strtolower($a->attempt->user->name))->values();
    }

    /** @return array{0: Quiz, 1: \App\Models\Question, 2: Collection<int, QuizAnswer>, 3: float} */
    protected function questionContext(): array
    {
        $quiz = $this->currentQuiz();
        abort_unless($quiz, 404);
        $this->authorize('manage', $quiz);
        $questionId = $this->currentQuestionId($quiz, $this->pendingPerQuestion($quiz));
        abort_unless($questionId, 404);
        $question = $quiz->questions->firstWhere('id', $questionId);

        return [$quiz, $question, $this->questionAnswers($quiz, $questionId), $quiz->pointsFor($question)];
    }

    public function selectQuestion(int $id): void
    {
        $this->questionId = $id;
        $this->selected = [];
        $this->resetErrorBag();
    }

    public function toggleAll(): void
    {
        [, , $answers] = $this->questionContext();
        $ids = $answers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->selected = count($this->selected) === count($ids) ? [] : $ids;
    }

    /** Makro: isi nilai yang sama untuk jawaban yang dicentang (belum disimpan). */
    public function applyBulk(): void
    {
        [, , $answers, $points] = $this->questionContext();
        $this->resetErrorBag('bulkScore');
        $score = str_replace(',', '.', trim($this->bulkScore));

        if (! is_numeric($score) || (float) $score < 0 || (float) $score > $points) {
            $this->addError('bulkScore', 'Nilai harus angka 0–'.angka($points, 1).'.');

            return;
        }

        $ids = $answers->pluck('id')->intersect(array_map('intval', $this->selected));
        if ($ids->isEmpty()) {
            $this->addError('bulkScore', 'Centang jawaban yang akan diberi nilai.');

            return;
        }

        foreach ($ids as $id) {
            $this->grades[$id]['score'] = $score;
        }

        Flux::toast(text: 'Nilai '.angka((float) $score, 2).' diisi untuk '.$ids->count().' jawaban. Periksa lalu tekan Simpan.');
    }

    /** Makro: saran nilai dari kata kunci (belum disimpan). Jawaban audio dilewati. */
    public function applyKeywords(EssayKeywordScorer $scorer): void
    {
        [, , $answers, $points] = $this->questionContext();
        $this->resetErrorBag(['keywords', 'keywordsNeeded']);
        $keywords = EssayKeywordScorer::parse($this->keywords);
        $needed = (int) $this->keywordsNeeded;

        if ($keywords === []) {
            $this->addError('keywords', 'Tulis minimal satu kata kunci.');

            return;
        }
        if ($needed < 1 || $needed > 50) {
            $this->addError('keywordsNeeded', 'Isi 1–50.');

            return;
        }

        $filled = 0;
        foreach ($answers as $answer) {
            if ($answer->audio_path) {
                continue;
            }
            $this->grades[$answer->id]['score'] = (string) $scorer->score((string) ($answer->answer['text'] ?? ''), $keywords, $needed, $points);
            $filled++;
        }

        Flux::toast(text: "Saran nilai diisi untuk {$filled} jawaban dari ".count($keywords).' kata kunci. Periksa lalu tekan Simpan.');
    }

    /**
     * Simpan nilai yang sudah diisi; kolom kosong tetap menunggu.
     *
     * @param  Collection<int, QuizAnswer>  $answers
     */
    protected function saveGrades(Collection $answers, GradeQuizAnswer $grader): int
    {
        $this->resetErrorBag($answers->map(fn ($a) => "grades.{$a->id}.score")->all());
        $saved = 0;

        foreach ($answers as $answer) {
            $input = $this->grades[$answer->id] ?? [];
            $score = str_replace(',', '.', trim((string) ($input['score'] ?? '')));

            if ($score === '') {
                continue;
            }

            if (! is_numeric($score)) {
                $this->addError("grades.{$answer->id}.score", 'Nilai wajib berupa angka.');

                continue;
            }

            try {
                $grader->handle($answer, auth()->user(), (float) $score, filled($input['feedback'] ?? null) ? trim($input['feedback']) : null);
                unset($this->grades[$answer->id]);
                $saved++;
            } catch (\InvalidArgumentException $e) {
                $this->addError("grades.{$answer->id}.score", $e->getMessage());
            }
        }

        return $saved;
    }

    /** Simpan semua nilai yang terisi untuk satu soal. */
    public function saveQuestion(GradeQuizAnswer $grader): void
    {
        [$quiz, , $answers] = $this->questionContext();
        $saved = $this->saveGrades($answers, $grader);
        $this->selected = [];

        if ($saved === 0 && $this->getErrorBag()->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Isi nilai dulu sebelum menyimpan.');

            return;
        }

        $left = $this->pendingPerQuestion($quiz)->sum();
        Flux::toast(variant: $this->getErrorBag()->isEmpty() ? 'success' : 'warning',
            text: "{$saved} nilai tersimpan.".($left ? " {$left} jawaban di quiz ini masih menunggu." : ' Semua jawaban quiz ini sudah dinilai.'));
    }

    /** Simpan semua nilai yang sudah diisi untuk satu peserta. */
    public function gradeAttempt(int $attemptId, GradeQuizAnswer $grader): void
    {
        $attempt = $this->pendingAttempts()->with('quiz')->findOrFail($attemptId);
        $this->authorize('manage', $attempt->quiz);
        $saved = $this->saveGrades($attempt->answers()->whereNull('score')->get(), $grader);

        if ($saved === 0 && $this->getErrorBag()->isEmpty()) {
            Flux::toast(variant: 'warning', text: 'Isi nilai dulu sebelum menyimpan.');

            return;
        }

        $left = $attempt->answers()->whereNull('score')->count();
        Flux::toast(variant: $this->getErrorBag()->isEmpty() ? 'success' : 'warning',
            text: "{$saved} nilai tersimpan untuk {$attempt->user->name}.".($left ? " {$left} jawaban masih menunggu." : ' Semua jawaban sudah dinilai.'));
    }

    public function with(): array
    {
        $data = [
            'total' => QuizAnswer::query()->whereNull('score')->whereHas('attempt', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)
                ->whereHas('quiz', fn ($q) => $this->scopeQuizzes($q)))->count(),
            'quizzes' => $this->gradableQuizzes()->with('classroom')->orderByDesc('id')->get(['id', 'title', 'classroom_id']),
        ];

        if ($this->view === 'peserta') {
            return $data + ['attempts' => $this->pendingAttempts()
                ->with(['user', 'quiz.classroom.language', 'quiz.questions', 'answers' => fn ($q) => $q->whereNull('score')->with('question')])
                ->orderBy('submitted_at')->orderBy('id')
                ->paginate(self::PER_PAGE)];
        }

        $quiz = $this->currentQuiz();
        $pending = $quiz ? $this->pendingPerQuestion($quiz) : collect();
        $questionId = $quiz ? $this->currentQuestionId($quiz, $pending) : null;
        $question = $questionId ? $quiz->questions->firstWhere('id', $questionId) : null;
        $answers = $question ? $this->questionAnswers($quiz, $question->id) : collect();

        return $data + [
            'currentQuiz' => $quiz,
            'pending' => $pending,
            'numbers' => $quiz ? $quiz->questions->pluck('id')->flip()->map(fn ($i) => $i + 1) : collect(),
            'question' => $question,
            'points' => $question ? $quiz->pointsFor($question) : 0,
            'answers' => $answers,
            'filledCount' => $answers->filter(fn ($a) => trim((string) ($this->grades[$a->id]['score'] ?? '')) !== '')->count(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-cjk-fonts />
    <x-page-header title="Perlu dinilai" :subtitle="$total.' jawaban esai/berbicara menunggu penilaian.'" />

    <flux:radio.group wire:model.live="view" variant="segmented" class="mb-3" aria-label="Tampilan">
        <flux:radio value="soal" icon="queue-list">Per soal</flux:radio>
        <flux:radio value="peserta" icon="user">Per peserta</flux:radio>
    </flux:radio.group>

    <div class="mb-4 grid gap-2 sm:grid-cols-2">
        <flux:select wire:model.live="quiz" aria-label="Quiz">
            @if ($view === 'peserta')
                <flux:select.option value="">Semua quiz</flux:select.option>
            @endif
            @foreach ($quizzes as $q)
                <flux:select.option :value="$q->id">{{ $q->title }} · {{ $q->classroom->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari nama peserta…" clearable aria-label="Cari peserta" />
    </div>

    @if ($view === 'soal')
        @if (! $currentQuiz || $pending->isEmpty())
            <x-empty-state icon="inbox-stack" title="Tidak ada antrean penilaian" description="Mantap! Semua jawaban sudah dinilai." />
        @else
            {{-- Pilih soal: hanya soal yang masih ada jawaban menunggu --}}
            <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1">
                @foreach ($currentQuiz->questions->filter(fn ($q) => $pending->has($q->id)) as $q)
                    <button type="button" wire:click="selectQuestion({{ $q->id }})" wire:key="qs-{{ $q->id }}" data-test="pick-{{ $q->id }}"
                            @class(['flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold ring-1 transition',
                                'bg-woka text-white ring-woka' => $question?->id === $q->id,
                                'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $question?->id !== $q->id])>
                        Soal {{ $numbers[$q->id] ?? '?' }}
                        <span @class(['rounded-full px-1.5 text-xs', 'bg-white/25' => $question?->id === $q->id, 'bg-kunyit/30 text-hutan dark:text-santan' => $question?->id !== $q->id])>{{ $pending[$q->id] }}</span>
                    </button>
                @endforeach
            </div>

            @if ($question)
                <x-card class="mb-3" data-test="grading-question">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="font-semibold text-hutan/60 dark:text-santan/60">Soal {{ $numbers[$question->id] ?? '?' }}</span>
                        <x-status-badge :status="$question->type" />
                        <span class="text-hutan/60 dark:text-santan/60">maks. {{ angka($points, 1) }} poin · {{ $answers->count() }} jawaban menunggu</span>
                    </div>
                    <x-reading-text :text="$question->body" :reading="$question->reading" as="p" class="mt-1 whitespace-pre-line font-semibold" />
                    @if ($question->hasImage())
                        <img src="{{ $question->mediaUrl() }}" alt="Gambar soal" class="mt-2 max-h-48 rounded-lg" loading="lazy">
                    @endif
                    @if ($question->explanation)
                        <p class="mt-2 rounded-lg bg-santan px-3 py-2 text-sm dark:bg-hutan-950"><span class="font-semibold">Contoh jawaban:</span> {{ $question->explanation }}</p>
                    @endif
                </x-card>

                {{-- Makro penilaian: isi banyak nilai sekaligus, lalu simpan --}}
                <x-card class="mb-3" x-data="{ open: false }" data-test="grading-macro">
                    <p class="text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">Makro penilaian</p>
                    <div class="mt-2 flex flex-wrap items-end gap-2">
                        <flux:button size="sm" wire:click="toggleAll">{{ count($selected) === $answers->count() ? 'Batal pilih semua' : 'Pilih semua ('.$answers->count().')' }}</flux:button>
                        <div class="flex items-end gap-1.5">
                            <div class="w-24 shrink-0">
                                <flux:input wire:model="bulkScore" inputmode="decimal" size="sm" :placeholder="'0–'.angka($points, 1)" aria-label="Nilai untuk yang dicentang" />
                            </div>
                            <flux:button size="sm" variant="primary" wire:click="applyBulk">Beri ke {{ count($selected) }} terpilih</flux:button>
                        </div>
                    </div>
                    <flux:error name="bulkScore" />

                    <button type="button" class="mt-3 text-sm font-semibold text-woka hover:underline dark:text-woka-light" x-on:click="open = ! open">
                        <span x-text="open ? 'Tutup nilai dari kata kunci' : 'Nilai otomatis dari kata kunci…'"></span>
                    </button>
                    <div x-show="open" x-collapse class="mt-2 space-y-2">
                        <flux:textarea wire:model="keywords" rows="2" label="Kata kunci (pisahkan dengan koma; alternatif dengan /)" placeholder="跑步/paobu, 游泳/youyong, 篮球/lanqiu" />
                        <flux:error name="keywords" />
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:input wire:model="keywordsNeeded" type="number" min="1" max="50" size="sm" label="Butuh berapa untuk nilai penuh" class="w-48" />
                            <flux:button size="sm" variant="primary" icon="sparkles" wire:click="applyKeywords">Isi nilai dari kata kunci</flux:button>
                        </div>
                        <flux:error name="keywordsNeeded" />
                        <p class="text-xs text-hutan/60 dark:text-santan/60">Contoh: "Sebutkan 3 olahraga" → kata kunci beberapa olahraga, butuh 3. Tiap kata kunci yang muncul menambah nilai. Pinyin tanpa tanda nada tetap terbaca. Hasilnya saran: periksa lalu Simpan.</p>
                    </div>
                </x-card>

                <ul class="space-y-2">
                    @foreach ($answers as $answer)
                        <li wire:key="qa-{{ $answer->id }}" data-test="row-{{ $answer->id }}"
                            @class(['rounded-2xl bg-white p-3 ring-1 dark:bg-hutan-900', 'ring-woka' => in_array((string) $answer->id, $selected, true), 'ring-woka-100 dark:ring-hutan-800' => ! in_array((string) $answer->id, $selected, true)])>
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" value="{{ $answer->id }}" wire:model.live="selected" class="size-5 shrink-0 accent-woka" aria-label="Pilih jawaban {{ $answer->attempt->user->name }}">
                                <span class="min-w-0 truncate font-semibold">{{ $answer->attempt->user->name }}</span>
                            </label>
                            @if ($answer->audio_path)
                                <audio controls preload="metadata" class="mt-2 w-full" src="{{ $answer->audioUrl() }}"></audio>
                            @else
                                @php
                                    $text = trim((string) ($answer->answer['text'] ?? ''));
                                @endphp
                                <p @class(['mt-1 whitespace-pre-line text-sm', 'italic text-hutan/40 dark:text-santan/40' => $text === ''])>{{ $text !== '' ? $text : '(kosong)' }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <div class="w-20 shrink-0">
                                    <flux:input wire:model.live.debounce.300ms="grades.{{ $answer->id }}.score" inputmode="decimal" size="sm" :placeholder="'0–'.angka($points, 1)" :aria-label="'Nilai '.$answer->attempt->user->name" />
                                </div>
                                @foreach (['0' => '0', (string) round($points / 2, 2) => '½', (string) $points => 'Penuh'] as $value => $label)
                                    <flux:button size="xs" wire:click="$set('grades.{{ $answer->id }}.score', '{{ $value }}')">{{ $label }}</flux:button>
                                @endforeach
                                <div class="min-w-0 flex-1 basis-40">
                                    <flux:input wire:model="grades.{{ $answer->id }}.feedback" size="sm" placeholder="Umpan balik (opsional)" :aria-label="'Umpan balik '.$answer->attempt->user->name" />
                                </div>
                            </div>
                            <flux:error name="grades.{{ $answer->id }}.score" />
                        </li>
                    @endforeach
                </ul>

                <div class="sticky bottom-0 z-20 -mx-4 mt-4 border-t border-woka-100 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border dark:border-hutan-800 dark:bg-hutan-900/95">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm" data-test="filled-count"><strong>{{ $filledCount }}</strong> dari {{ $answers->count() }} nilai terisi</p>
                        <flux:button variant="primary" icon="check" wire:click="saveQuestion" wire:loading.attr="disabled" wire:target="saveQuestion">Simpan nilai soal ini</flux:button>
                    </div>
                </div>
            @endif
        @endif
    @else
        <div class="space-y-4">
            @forelse ($attempts as $attempt)
                @php
                    $order = collect($attempt->question_order ?? [])->flip();
                    $numbers = $attempt->quiz->questions->pluck('id')->flip();
                    $attemptAnswers = $attempt->answers->sortBy(fn ($a) => $order[$a->question_id] ?? $numbers[$a->question_id] ?? 0)->values();
                @endphp
                <x-card wire:key="ga-{{ $attempt->id }}" data-test="attempt-{{ $attempt->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2 border-b border-woka-100 pb-3 dark:border-hutan-800">
                        <div class="min-w-0">
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $attempt->quiz->title }} · {{ $attempt->quiz->classroom->label() }}</p>
                            <p class="font-display text-lg font-bold">{{ $attempt->user->name }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">Dikumpulkan {{ $attempt->submitted_at ? tanggal_jam($attempt->submitted_at) : '–' }}@if ($attempt->auto_submitted) · otomatis @endif</p>
                        </div>
                        <flux:badge color="amber">{{ $attemptAnswers->count() }} jawaban perlu dinilai</flux:badge>
                    </div>

                    <ol class="mt-3 space-y-4">
                        @foreach ($attemptAnswers as $answer)
                            @php
                                $q = $attempt->quiz->questions->firstWhere('id', $answer->question_id) ?? $answer->question;
                                $max = $attempt->quiz->pointsFor($q);
                                $number = ($order[$answer->question_id] ?? $numbers[$answer->question_id] ?? 0) + 1;
                            @endphp
                            <li wire:key="gq-{{ $answer->id }}">
                                <div class="rounded-xl bg-santan p-3 dark:bg-hutan-950">
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="font-semibold text-hutan/60 dark:text-santan/60">Soal {{ $number }}</span>
                                        <x-status-badge :status="$answer->question->type" />
                                        <span class="text-hutan/60 dark:text-santan/60">maks. {{ angka($max, 1) }} poin</span>
                                    </div>
                                    <x-reading-text :text="$answer->question->body" :reading="$answer->question->reading" as="p" class="mt-1 whitespace-pre-line text-sm font-semibold" />
                                    @if ($answer->question->hasImage())
                                        <img src="{{ $answer->question->mediaUrl() }}" alt="Gambar soal" class="mt-2 max-h-40 rounded-lg" loading="lazy">
                                    @endif
                                    @if ($answer->question->explanation)
                                        <p class="mt-2 text-xs text-hutan/70 dark:text-santan/70">Contoh jawaban: {{ $answer->question->explanation }}</p>
                                    @endif
                                </div>
                                @if ($answer->audio_path)
                                    <audio controls preload="metadata" class="mt-2 w-full" src="{{ $answer->audioUrl() }}"></audio>
                                @else
                                    <p class="mt-2 whitespace-pre-line rounded-xl border border-woka-100 px-3 py-2 text-sm dark:border-hutan-800">{{ $answer->answer['text'] ?? '(kosong)' }}</p>
                                @endif
                                <div class="mt-2 grid grid-cols-[7rem_1fr] gap-2">
                                    <flux:input wire:model="grades.{{ $answer->id }}.score" inputmode="decimal" :placeholder="'0–'.angka($max, 1)" :aria-label="'Nilai soal '.$number" />
                                    <flux:input wire:model="grades.{{ $answer->id }}.feedback" placeholder="Umpan balik (opsional)" :aria-label="'Umpan balik soal '.$number" />
                                </div>
                                <flux:error name="grades.{{ $answer->id }}.score" />
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-woka-100 pt-3 dark:border-hutan-800">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">Kolom nilai yang kosong tetap menunggu penilaian.</p>
                        <flux:button variant="primary" icon="check" wire:click="gradeAttempt({{ $attempt->id }})" wire:loading.attr="disabled" wire:target="gradeAttempt({{ $attempt->id }})">
                            Simpan semua nilai
                        </flux:button>
                    </div>
                </x-card>
            @empty
                <x-empty-state icon="inbox-stack" title="Tidak ada antrean penilaian" description="Mantap! Semua jawaban sudah dinilai." />
            @endforelse
        </div>

        <div class="mt-4">{{ $attempts->links() }}</div>
    @endif
</div>
