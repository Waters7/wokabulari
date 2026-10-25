<?php

use App\Actions\Quizzes\GradeQuizAnswer;
use App\Enums\AttemptStatus;
use App\Models\QuizAnswer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Perlu dinilai')] class extends Component {
    #[Url]
    public ?int $quiz = null;

    /** @var array<int, array{score: mixed, feedback: mixed}> */
    public array $grades = [];

    protected function pending(): Builder
    {
        $user = auth()->user();

        return QuizAnswer::query()
            ->whereNull('score')
            ->whereHas('attempt', fn ($q) => $q->where('status', AttemptStatus::NeedsGrading)
                ->whereHas('quiz', fn ($q) => $q->when($this->quiz, fn ($q) => $q->whereKey($this->quiz))
                    ->when(! $user->isSuperAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('teacher_id', $user->id)->orWhereHas('classroom', fn ($c) => $c->where('teacher_id', $user->id))))));
    }

    public function grade(int $answerId, GradeQuizAnswer $grader): void
    {
        $answer = $this->pending()->with('attempt.quiz')->findOrFail($answerId);
        $this->authorize('manage', $answer->attempt->quiz);
        $this->resetErrorBag();

        $input = $this->grades[$answerId] ?? [];
        $score = str_replace(',', '.', (string) ($input['score'] ?? ''));

        if (! is_numeric($score)) {
            $this->addError("grades.{$answerId}.score", 'Nilai wajib berupa angka.');

            return;
        }

        try {
            $grader->handle($answer, auth()->user(), (float) $score, $input['feedback'] ?? null);
        } catch (\InvalidArgumentException $e) {
            $this->addError("grades.{$answerId}.score", $e->getMessage());

            return;
        }

        unset($this->grades[$answerId]);
        Flux::toast(variant: 'success', text: 'Nilai tersimpan.');
    }

    public function with(): array
    {
        return [
            'answers' => $this->pending()->with(['question', 'attempt.user', 'attempt.quiz.classroom.language'])->oldest()->limit(30)->get(),
            'total' => $this->pending()->count(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-cjk-fonts />
    <x-page-header title="Perlu dinilai" :subtitle="$total.' jawaban esai/berbicara menunggu penilaian.'" />

    <div class="space-y-3">
        @forelse ($answers as $answer)
            @php($points = $answer->attempt->quiz->pointsFor($answer->attempt->quiz->questions->firstWhere('id', $answer->question_id) ?? $answer->question))
            <x-card wire:key="ga-{{ $answer->id }}">
                <p class="text-xs text-hutan/60">{{ $answer->attempt->quiz->title }} · {{ $answer->attempt->quiz->classroom->label() }}</p>
                <p class="font-semibold">{{ $answer->attempt->user->name }}</p>
                <div class="mt-2 rounded-xl bg-santan p-3 dark:bg-hutan-950">
                    <x-status-badge :status="$answer->question->type" />
                    <x-reading-text :text="$answer->question->body" :reading="$answer->question->reading" as="p" class="mt-1 whitespace-pre-line text-sm font-semibold" />
                </div>
                @if ($answer->audio_path)
                    <audio controls preload="metadata" class="mt-3 w-full" src="{{ $answer->audioUrl() }}"></audio>
                @else
                    <p class="mt-3 whitespace-pre-line text-sm">{{ $answer->answer['text'] ?? '(kosong)' }}</p>
                @endif
                <form wire:submit="grade({{ $answer->id }})" class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr_auto] sm:items-end">
                    <flux:input wire:model="grades.{{ $answer->id }}.score" inputmode="decimal" :label="'Nilai (0–'.angka($points, 1).')'" />
                    <flux:input wire:model="grades.{{ $answer->id }}.feedback" label="Umpan balik" />
                    <flux:button type="submit" variant="primary" icon="check">Simpan</flux:button>
                    <flux:error name="grades.{{ $answer->id }}.score" class="sm:col-span-3" />
                </form>
            </x-card>
        @empty
            <x-empty-state icon="inbox-stack" title="Tidak ada antrean penilaian" description="Mantap! Semua jawaban sudah dinilai." />
        @endforelse
    </div>
</div>
