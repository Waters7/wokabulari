<?php

use App\Actions\Evaluations\SubmitEvaluation;
use App\Enums\EvaluationQuestionType;
use App\Http\Requests\EvaluationResponseRequest;
use App\Models\ProgramEvaluation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Isi survei')] class extends Component {
    public ProgramEvaluation $evaluation;

    /** @var array<int|string, mixed> question_id => jawaban */
    public array $answers = [];

    public bool $submitted = false;

    public function mount(ProgramEvaluation $evaluation): void
    {
        abort_if(! $evaluation->isPublished() || $evaluation->roleFor(auth()->user()) === null, 404);
        $this->evaluation = $evaluation->load('questions');
        $this->submitted = $evaluation->hasResponded(auth()->user());
    }

    public function submit(SubmitEvaluation $submit): void
    {
        $this->authorize('respondSurvey', $this->evaluation);
        $this->resetErrorBag();
        $questions = $this->questions();

        $validator = Validator::make(['answers' => $this->answers], EvaluationResponseRequest::rulesFor($questions), [], EvaluationResponseRequest::attributesFor($questions));
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->messages());
        }

        try {
            $submit->handle($this->evaluation, auth()->user(), $validator->validated()['answers'] ?? []);
        } catch (\InvalidArgumentException $e) {
            $this->addError('answers', $e->getMessage());

            return;
        }

        $this->submitted = true;
        $this->answers = [];
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\EvaluationQuestion> */
    protected function questions()
    {
        return $this->evaluation->questionsFor((string) $this->evaluation->roleFor(auth()->user()));
    }

    public function with(): array
    {
        return [
            'questions' => $this->questions(),
            'likertLabels' => EvaluationQuestionType::likertLabels(),
            'open' => $this->evaluation->isOpen(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header :title="$evaluation->title" :back="route('evaluations.index')"
                   :subtitle="$evaluation->category->label().' · tenggat '.tanggal_jam($evaluation->closes_at)" />

    @if ($submitted)
        <x-card class="text-center" data-test="evaluation-thanks">
            <flux:icon.check-badge class="mx-auto size-14 text-woka" />
            <p class="mt-2 font-display text-xl font-bold">Makasih banyak!</p>
            <p class="text-sm text-hutan/70 dark:text-santan/70">Jawaban ngana so tersimpan{{ $evaluation->is_anonymous ? ' tanpa nama' : '' }}. Tim kerja akan memakai masukan ini untuk perbaikan.</p>
            <flux:button class="mt-4" :href="route('evaluations.index')" wire:navigate>Kembali ke daftar survei</flux:button>
        </x-card>
    @elseif (! $open)
        <x-empty-state icon="lock-closed" :title="$evaluation->isClosed() ? 'Survei sudah ditutup' : 'Survei belum dibuka'"
                       :description="$evaluation->isClosed() ? 'Tenggatnya sudah lewat.' : 'Dibuka '.tanggal_jam($evaluation->opens_at).'.'" />
    @else
        @if ($evaluation->description)
            <p class="mb-3 text-sm">{{ $evaluation->description }}</p>
        @endif
        @if ($evaluation->is_anonymous)
            <flux:callout icon="eye-slash" class="mb-4" text="Survei ini anonim: nama ngana tidak disimpan bersama jawaban." />
        @endif

        <form wire:submit="submit" class="space-y-3">
            <flux:error name="answers" />

            @foreach ($questions as $i => $question)
                <x-card wire:key="fq-{{ $question->id }}" class="min-w-0">
                    @if ($question->aspect)
                        <p class="text-xs font-semibold uppercase tracking-wide text-woka dark:text-woka-light">{{ $question->aspect }}</p>
                    @endif
                    <p class="mb-3 font-semibold">{{ $i + 1 }}. {{ $question->body }} @if ($question->is_required)<span class="text-red-600" aria-label="wajib">*</span>@endif</p>

                    @if ($question->type === EvaluationQuestionType::Likert)
                        <div class="grid grid-cols-5 gap-1" role="radiogroup" aria-label="{{ $question->body }}">
                            @foreach ($likertLabels as $score => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" class="peer sr-only" wire:model="answers.{{ $question->id }}" value="{{ $score }}" name="q{{ $question->id }}">
                                    <span class="flex min-h-14 flex-col items-center justify-center rounded-xl bg-santan px-1 py-2 text-center ring-1 ring-woka-100 transition peer-checked:bg-woka peer-checked:text-white peer-checked:ring-woka peer-focus-visible:ring-2 dark:bg-hutan-800 dark:ring-hutan-700">
                                        <span class="font-display text-lg font-bold leading-none">{{ $score }}</span>
                                        <span class="mt-1 text-[10px] leading-tight">{{ $label }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @elseif ($question->type === EvaluationQuestionType::Choice)
                        <flux:radio.group wire:model="answers.{{ $question->id }}">
                            @foreach ($question->options ?? [] as $option)
                                <flux:radio :value="$option" :label="$option" />
                            @endforeach
                        </flux:radio.group>
                    @else
                        <flux:textarea wire:model="answers.{{ $question->id }}" rows="3" placeholder="Tulis masukan ngana…" />
                    @endif
                    <flux:error name="answers.{{ $question->id }}" />
                </x-card>
            @endforeach

            <flux:button type="submit" variant="primary" class="w-full" icon="paper-airplane">Kirim jawaban</flux:button>
        </form>
    @endif
</div>
