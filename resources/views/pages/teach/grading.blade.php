<?php

use App\Actions\Quizzes\GradeQuizAnswer;
use App\Enums\AttemptStatus;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Perlu dinilai')] class extends Component {
    use WithPagination;

    /** Peserta per halaman: semua jawaban satu peserta tampil dalam satu kartu. */
    public const PER_PAGE = 10;

    #[Url]
    public ?int $quiz = null;

    #[Url(as: 'cari')]
    public string $search = '';

    /** @var array<int, array{score: mixed, feedback: mixed}> */
    public array $grades = [];

    public function updatingQuiz(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Quiz yang boleh dinilai pengguna ini. */
    protected function scopeQuizzes(Builder $query): Builder
    {
        $user = auth()->user();

        return $query->when(! $user->isSuperAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('teacher_id', $user->id)->orWhereHas('classroom', fn ($c) => $c->taughtBy($user))));
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

    /** Simpan semua nilai yang sudah diisi untuk satu peserta; kolom yang kosong tetap menunggu. */
    public function gradeAttempt(int $attemptId, GradeQuizAnswer $grader): void
    {
        $attempt = $this->pendingAttempts()->with('quiz')->findOrFail($attemptId);
        $this->authorize('manage', $attempt->quiz);

        $answers = $attempt->answers()->whereNull('score')->get();
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
        $attempts = $this->pendingAttempts()
            ->with(['user', 'quiz.classroom.language', 'quiz.questions', 'answers' => fn ($q) => $q->whereNull('score')->with('question')])
            ->orderBy('submitted_at')->orderBy('id')
            ->paginate(self::PER_PAGE);

        return [
            'attempts' => $attempts,
            'total' => QuizAnswer::query()->whereNull('score')->whereHas('attempt', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)
                ->whereHas('quiz', fn ($q) => $this->scopeQuizzes($q)))->count(),
            'quizzes' => $this->scopeQuizzes(Quiz::query())
                ->whereHas('attempts', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading))
                ->with('classroom')->orderByDesc('id')->get(['id', 'title', 'classroom_id']),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-cjk-fonts />
    <x-page-header title="Perlu dinilai" :subtitle="$total.' jawaban esai/berbicara menunggu penilaian · dikelompokkan per peserta.'" />

    <div class="mb-4 grid gap-2 sm:grid-cols-2">
        <flux:select wire:model.live="quiz" aria-label="Quiz">
            <flux:select.option value="">Semua quiz</flux:select.option>
            @foreach ($quizzes as $q)
                <flux:select.option :value="$q->id">{{ $q->title }} · {{ $q->classroom->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari nama peserta…" clearable aria-label="Cari peserta" />
    </div>

    <div class="space-y-4">
        @forelse ($attempts as $attempt)
            @php
                $order = collect($attempt->question_order ?? [])->flip();
                $numbers = $attempt->quiz->questions->pluck('id')->flip();
                $answers = $attempt->answers->sortBy(fn ($a) => $order[$a->question_id] ?? $numbers[$a->question_id] ?? 0)->values();
            @endphp
            <x-card wire:key="ga-{{ $attempt->id }}" data-test="attempt-{{ $attempt->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-woka-100 pb-3 dark:border-hutan-800">
                    <div class="min-w-0">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $attempt->quiz->title }} · {{ $attempt->quiz->classroom->label() }}</p>
                        <p class="font-display text-lg font-bold">{{ $attempt->user->name }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">Dikumpulkan {{ $attempt->submitted_at ? tanggal_jam($attempt->submitted_at) : '–' }}@if ($attempt->auto_submitted) · otomatis @endif</p>
                    </div>
                    <flux:badge color="amber">{{ $answers->count() }} jawaban perlu dinilai</flux:badge>
                </div>

                <ol class="mt-3 space-y-4">
                    @foreach ($answers as $answer)
                        @php
                            $question = $attempt->quiz->questions->firstWhere('id', $answer->question_id) ?? $answer->question;
                            $points = $attempt->quiz->pointsFor($question);
                            $number = ($order[$answer->question_id] ?? $numbers[$answer->question_id] ?? 0) + 1;
                        @endphp
                        <li wire:key="gq-{{ $answer->id }}">
                            <div class="rounded-xl bg-santan p-3 dark:bg-hutan-950">
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="font-semibold text-hutan/60 dark:text-santan/60">Soal {{ $number }}</span>
                                    <x-status-badge :status="$answer->question->type" />
                                    <span class="text-hutan/60 dark:text-santan/60">maks. {{ angka($points, 1) }} poin</span>
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
                                <flux:input wire:model="grades.{{ $answer->id }}.score" inputmode="decimal" :placeholder="'0–'.angka($points, 1)" :aria-label="'Nilai soal '.$number" />
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
</div>
