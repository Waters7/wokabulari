<?php

use App\Actions\Evaluations\PublishEvaluation;
use App\Actions\Evaluations\SaveEvaluation;
use App\Enums\EvaluationAudience;
use App\Enums\EvaluationCategory;
use App\Enums\EvaluationQuestionType;
use App\Http\Requests\ProgramEvaluationRequest;
use App\Models\Batch;
use App\Models\ProgramEvaluation;
use App\Services\Evaluations\EvaluationTemplates;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Survei')] class extends Component {
    public ?ProgramEvaluation $evaluation = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $questions = [];

    public bool $locked = false;

    public function mount(?ProgramEvaluation $evaluation = null): void
    {
        $this->evaluation = $evaluation?->exists ? $evaluation->load(['batches', 'questions']) : null;
        $this->authorize($this->evaluation ? 'update' : 'create', $this->evaluation ?? ProgramEvaluation::class);

        if ($this->evaluation) {
            $e = $this->evaluation;
            $this->locked = $e->responses()->exists();
            $this->form = [
                'title' => $e->title, 'description' => $e->description, 'category' => $e->category->value,
                'batch_ids' => $e->batches->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'target_participants' => $e->target_participants, 'target_teachers' => $e->target_teachers,
                'is_anonymous' => $e->is_anonymous,
                'opens_at' => $e->opens_at?->format('Y-m-d\TH:i') ?? '',
                'closes_at' => $e->closes_at->format('Y-m-d\TH:i'),
            ];
            $this->questions = $e->questions->map(fn ($q) => [
                'aspect' => (string) $q->aspect, 'type' => $q->type->value, 'body' => $q->body,
                'options' => implode("\n", $q->options ?? []), 'audience' => $q->audience->value, 'is_required' => $q->is_required,
            ])->all();

            return;
        }

        $this->form = [
            'title' => 'Survei Sarana & Prasarana', 'description' => '', 'category' => EvaluationCategory::Facility->value,
            'batch_ids' => array_filter([(string) Batch::current()?->id]),
            'target_participants' => true, 'target_teachers' => true, 'is_anonymous' => true,
            'opens_at' => '', 'closes_at' => now()->addWeek()->setTime(23, 59)->format('Y-m-d\TH:i'),
        ];
        $this->questions = EvaluationTemplates::questions(EvaluationCategory::Facility);
    }

    public function useTemplate(): void
    {
        abort_if($this->locked, 403);
        $this->questions = EvaluationTemplates::questions(EvaluationCategory::tryFrom($this->form['category']) ?? EvaluationCategory::Facility);
    }

    public function addQuestion(string $type): void
    {
        abort_if($this->locked, 403);
        $this->questions[] = [
            'aspect' => '', 'type' => (EvaluationQuestionType::tryFrom($type) ?? EvaluationQuestionType::Likert)->value,
            'body' => '', 'options' => '', 'audience' => EvaluationAudience::All->value, 'is_required' => $type !== EvaluationQuestionType::Text->value,
        ];
    }

    public function removeQuestion(int $index): void
    {
        abort_if($this->locked, 403);
        unset($this->questions[$index]);
        $this->questions = array_values($this->questions);
    }

    public function moveQuestion(int $index, int $direction): void
    {
        abort_if($this->locked, 403);
        $target = $index + $direction;

        if (isset($this->questions[$index], $this->questions[$target])) {
            [$this->questions[$index], $this->questions[$target]] = [$this->questions[$target], $this->questions[$index]];
        }
    }

    public function save(SaveEvaluation $save, PublishEvaluation $publish): void
    {
        $this->persist($save, $publish, andPublish: false);
    }

    public function saveAndPublish(SaveEvaluation $save, PublishEvaluation $publish): void
    {
        $this->persist($save, $publish, andPublish: true);
    }

    protected function persist(SaveEvaluation $save, PublishEvaluation $publish, bool $andPublish): void
    {
        $this->authorize($this->evaluation ? 'update' : 'create', $this->evaluation ?? ProgramEvaluation::class);
        $this->resetErrorBag();

        $validator = Validator::make($this->form + ['questions' => $this->questions], ProgramEvaluationRequest::rulesFor(), ProgramEvaluationRequest::messagesFor(), ProgramEvaluationRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($m, $k) => [str_starts_with($k, 'questions') ? $k : 'form.'.$k => $m])->all());
        }

        try {
            $evaluation = $save->handle($validator->validated(), auth()->user(), $this->evaluation);
            $message = 'Survei tersimpan.';

            if ($andPublish && ! $evaluation->isPublished()) {
                $notified = $publish->publish($evaluation, auth()->user());
                $message = $notified ? "Survei terbit; {$notified} responden diberi tahu." : 'Survei terbit dan akan dibuka sesuai jadwal.';
            }
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.closes_at', $e->getMessage());
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        session()->flash('status', $message);
        $this->redirectRoute('manage.evaluations.edit', $evaluation, navigate: true);
    }

    public function with(): array
    {
        return [
            'batches' => Batch::query()->orderByDesc('is_active')->orderByDesc('start_date')->get(),
            'categories' => EvaluationCategory::cases(),
            'types' => EvaluationQuestionType::cases(),
            'audiences' => EvaluationAudience::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header :title="$evaluation ? 'Ubah survei' : 'Survei baru'" :back="route('manage.evaluations.index')"
                   subtitle="Pilih angkatan dan responden, atur tenggat, lalu susun pertanyaan." />

    @if (session('status'))
        <flux:callout icon="check-circle" color="green" class="mb-4" :text="session('status')" />
    @endif

    @if ($locked)
        <flux:callout icon="lock-closed" color="amber" class="mb-4"
                      heading="Sudah ada jawaban masuk"
                      text="Agar hasil tetap sahih, sasaran dan pertanyaan dikunci. Judul, keterangan, dan tenggat masih bisa diubah." />
    @endif

    <form wire:submit="save" class="space-y-4">
        <x-card title="Informasi">
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.title" label="Judul" class="sm:col-span-2" required />
                <flux:select wire:model="form.category" label="Jenis" :disabled="$locked">
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="form.is_anonymous" label="Anonim" :disabled="$locked"
                             description="Nama responden tidak disimpan; hanya hash pencegah isi ganda." />
                <flux:textarea wire:model="form.description" label="Keterangan untuk responden" rows="2" class="sm:col-span-2"
                               placeholder="Bantu kami memperbaiki fasilitas belajar dan asrama." />
            </div>
        </x-card>

        <x-card title="Sasaran & tenggat">
            <div class="space-y-4">
                <flux:checkbox.group wire:model="form.batch_ids" label="Angkatan" :disabled="$locked">
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($batches as $batch)
                            <flux:checkbox :value="(string) $batch->id" :label="$batch->name.($batch->is_active ? ' (aktif)' : '')" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
                <flux:error name="form.batch_ids" />

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:switch wire:model="form.target_participants" label="Peserta angkatan" :disabled="$locked" />
                    <flux:switch wire:model="form.target_teachers" label="Tim pengajar angkatan" :disabled="$locked"
                                 description="Pengajar yang mengajar kelas di angkatan terpilih." />
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="form.opens_at" type="datetime-local" label="Dibuka (opsional)" :disabled="$locked"
                                description="Kosongkan agar langsung dibuka saat terbit." />
                    <flux:input wire:model="form.closes_at" type="datetime-local" label="Tenggat" required />
                </div>
                <flux:error name="form.target_participants" />
            </div>
        </x-card>

        <x-card :title="'Pertanyaan ('.count($questions).')'">
            @unless ($locked)
                <x-slot:action>
                    <flux:button size="sm" icon="sparkles" wire:click="useTemplate"
                                 wire:confirm="Ganti semua pertanyaan dengan templat jenis survei terpilih?">Pakai templat</flux:button>
                </x-slot:action>
            @endunless
            <flux:error name="questions" />

            <div class="space-y-3">
                @foreach ($questions as $i => $question)
                    <div wire:key="q-{{ $i }}" class="rounded-xl bg-santan p-3 ring-1 ring-woka-100 dark:bg-hutan-800 dark:ring-hutan-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <span class="text-sm font-bold">#{{ $i + 1 }}</span>
                            @unless ($locked)
                                <div class="flex">
                                    <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveQuestion({{ $i }}, -1)" aria-label="Naik" :disabled="$i === 0" />
                                    <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveQuestion({{ $i }}, 1)" aria-label="Turun" :disabled="$i === count($questions) - 1" />
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeQuestion({{ $i }})" aria-label="Hapus pertanyaan" />
                                </div>
                            @endunless
                        </div>

                        @if ($locked)
                            <p class="text-sm font-semibold">{{ $question['body'] }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ EvaluationQuestionType::from($question['type'])->label() }} · {{ $question['aspect'] ?: 'Umum' }} · {{ EvaluationAudience::from($question['audience'])->label() }}</p>
                        @else
                            <div class="grid gap-2 sm:grid-cols-3">
                                <flux:select wire:model.live="questions.{{ $i }}.type" label="Tipe" size="sm">
                                    @foreach ($types as $type)
                                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="questions.{{ $i }}.aspect" label="Aspek" size="sm" placeholder="Ruang kelas" />
                                <flux:select wire:model="questions.{{ $i }}.audience" label="Untuk" size="sm">
                                    @foreach ($audiences as $audience)
                                        <flux:select.option :value="$audience->value">{{ $audience->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:textarea wire:model="questions.{{ $i }}.body" label="Pertanyaan" rows="2" class="sm:col-span-3" />
                                @if ($question['type'] === 'choice')
                                    <flux:textarea wire:model="questions.{{ $i }}.options" label="Pilihan (satu per baris)" rows="3" class="sm:col-span-3" />
                                @endif
                                <flux:switch wire:model="questions.{{ $i }}.is_required" label="Wajib diisi" class="sm:col-span-3" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @unless ($locked)
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" icon="plus" wire:click="addQuestion('likert')">Skala 1–5</flux:button>
                    <flux:button size="sm" icon="plus" wire:click="addQuestion('choice')">Pilihan ganda</flux:button>
                    <flux:button size="sm" icon="plus" wire:click="addQuestion('text')">Isian</flux:button>
                </div>
            @endunless
        </x-card>

        <div class="flex flex-wrap justify-end gap-2">
            <flux:button :href="route('manage.evaluations.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit">{{ $evaluation?->isPublished() ? 'Simpan' : 'Simpan draf' }}</flux:button>
            @unless ($evaluation?->isPublished())
                <flux:button variant="primary" icon="paper-airplane" wire:click="saveAndPublish">Simpan & terbitkan</flux:button>
            @endunless
        </div>
    </form>
</div>
