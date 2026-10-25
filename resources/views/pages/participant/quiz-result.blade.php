<?php

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\QuizType;
use App\Models\QuizAttempt;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hasil quiz')] class extends Component {
    public QuizAttempt $attempt;

    public function mount(QuizAttempt $attempt): void
    {
        $this->authorize('viewAttempt', [App\Models\Quiz::class, $attempt]);
        $this->attempt = $attempt->load(['quiz.classroom.language', 'user.profile']);
    }

    public function with(): array
    {
        $quiz = $this->attempt->quiz;
        $visible = $quiz->resultsVisible() && $this->attempt->status === AttemptStatus::Graded;
        $data = ['quiz' => $quiz, 'visible' => $visible, 'isCjk' => $quiz->classroom->language->isCjk()];

        if (! $visible) {
            return $data;
        }

        // Kunci jawaban baru dimuat setelah attempt dikumpulkan dan hasil dirilis.
        $questions = $quiz->questions()->with('options')->get()->keyBy('id');
        $answers = $this->attempt->answers()->get()->keyBy('question_id');

        $data['items'] = collect($this->attempt->question_order)->map(fn ($id) => ['question' => $questions->get($id), 'answer' => $answers->get($id)])->filter(fn ($i) => $i['question']);
        $data['sections'] = $questions->groupBy(fn ($q) => $q->pivot->section ?: 'Umum')->map(fn ($group) => [
            'score' => $group->sum(fn ($q) => $answers->get($q->id)?->score ?? 0),
            'max' => $group->sum(fn ($q) => $quiz->pointsFor($q)),
        ]);

        return $data;
    }
}; ?>

<div class="mx-auto max-w-2xl">
    @if ($isCjk)<x-cjk-fonts />@endif
    <x-page-header :title="'Hasil: '.$quiz->title" :back="route('quizzes.index')" />

    <x-card class="mb-4 text-center">
        <p class="text-sm text-hutan/60">Dikumpulkan {{ tanggal_jam($attempt->submitted_at) }} @if ($attempt->auto_submitted)· otomatis saat waktu habis @endif</p>
        @if ($visible)
            <p class="mt-2 font-display text-6xl font-extrabold {{ $attempt->passed() ? 'text-woka' : 'text-rica' }}">{{ angka($attempt->percentage, 0) }}</p>
            <p class="text-sm">{{ angka($attempt->score, 1) }} dari {{ angka($attempt->max_score, 1) }} poin</p>
            <div class="mt-2">
                <flux:badge :color="$attempt->passed() ? 'green' : 'red'" size="lg">{{ $attempt->passed() ? 'Lulus — mantap!' : 'Belum lulus — ayo jo latihan lagi' }}</flux:badge>
            </div>
            @if ($quiz->type === QuizType::Tryout)
                <p class="mt-3 text-sm">Skor tryout <strong>{{ angka($attempt->scaledScore()) }}</strong> / {{ $quiz->score_scale }}
                    @if ($attempt->user->profile?->target_score) · target {{ $attempt->user->profile->target_score }} @endif
                </p>
            @endif
        @elseif ($attempt->status === AttemptStatus::NeedsGrading)
            <flux:icon.clock class="mx-auto mt-3 size-12 text-kunyit" />
            <p class="mt-2 font-semibold">Jawaban esai/berbicara sedang dinilai pengajar.</p>
        @else
            <flux:icon.check-circle class="mx-auto mt-3 size-12 text-woka" />
            <p class="mt-2 font-semibold">Jawaban terkumpul.</p>
            <p class="text-sm text-hutan/60">{{ $quiz->result_visibility === \App\Enums\ResultVisibility::AfterClose ? 'Hasil tampil setelah quiz ditutup'.($quiz->closes_at ? ' ('.tanggal_jam($quiz->closes_at).')' : '').'.' : 'Hasil akan dirilis oleh pengajar.' }}</p>
        @endif
    </x-card>

    @if ($visible)
        @if ($quiz->type === QuizType::Tryout && $sections->count() > 1)
            <div class="mb-4 grid grid-cols-2 gap-3">
                @foreach ($sections as $name => $section)
                    <x-stat :label="$name" :value="angka($section['score'], 1).' / '.angka($section['max'], 1)" icon="document-text" />
                @endforeach
            </div>
        @endif

        <div class="space-y-2">
            @foreach ($items as $i => $item)
                @php($q = $item['question'])
                @php($a = $item['answer'])
                @php($given = $a?->answer ?? [])
                <x-card wire:key="r-{{ $q->id }}">
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-semibold text-hutan/60">Soal {{ $loop->iteration }} · <x-status-badge :status="$q->type" /></span>
                        <span @class(['font-bold', 'text-woka' => $a?->is_correct, 'text-rica' => $a && $a->is_correct === false])>
                            {{ angka($a?->score ?? 0, 1) }}/{{ angka($quiz->pointsFor($q), 1) }}
                        </span>
                    </div>
                    <x-reading-text :text="$q->body" :reading="$q->reading" display="ruby" as="p" class="whitespace-pre-line font-semibold" />

                    <div class="mt-2 space-y-1 text-sm">
                        @if ($q->type->usesChoice())
                            @php($chosen = $q->options->firstWhere('id', $given['option'] ?? null))
                            @php($correct = $q->options->firstWhere('is_correct', true))
                            <p>Jawabanmu: <strong>{{ $chosen ? ($q->type === QuestionType::Tone ? $chosen->reading : $chosen->body) : '–' }}</strong></p>
                            @unless ($a?->is_correct)
                                <p class="text-woka-700 dark:text-woka-light">Jawaban benar: <strong>{{ $q->type === QuestionType::Tone ? $correct?->reading : $correct?->body }}</strong></p>
                            @endunless
                        @elseif ($q->type === QuestionType::ShortAnswer)
                            <p>Jawabanmu: <strong>{{ $given['text'] ?? '–' }}</strong></p>
                            @unless ($a?->is_correct)
                                <p class="text-woka-700 dark:text-woka-light">Jawaban benar: <strong>{{ $q->options->pluck('body')->join(' / ') }}</strong></p>
                            @endunless
                        @elseif ($q->type === QuestionType::Matching)
                            @foreach ($q->options as $option)
                                @php($pick = $given['pairs'][$option->id] ?? null)
                                <p>{{ $option->body }} → <strong @class(['text-rica' => \App\Services\QuizGrader::normalize($pick) !== \App\Services\QuizGrader::normalize($option->match_key)])>{{ $pick ?: '–' }}</strong>
                                    @if (\App\Services\QuizGrader::normalize($pick) !== \App\Services\QuizGrader::normalize($option->match_key)) <span class="text-woka-700">({{ $option->match_key }})</span>@endif
                                </p>
                            @endforeach
                        @elseif ($q->type === QuestionType::Speaking)
                            @if ($a?->audio_path)<audio controls class="w-full" src="{{ $a->audioUrl() }}"></audio>@else <p>Tidak ada rekaman.</p> @endif
                        @else
                            <p class="whitespace-pre-line">{{ $given['text'] ?? '–' }}</p>
                        @endif
                        @if ($a?->teacher_feedback)
                            <p class="rounded-lg bg-kunyit-50 px-2 py-1 text-kunyit-700">Umpan balik: {{ $a->teacher_feedback }}</p>
                        @endif
                        @if ($q->explanation)
                            <p class="text-xs text-hutan/70 dark:text-santan/70">💡 {{ $q->explanation }}</p>
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
