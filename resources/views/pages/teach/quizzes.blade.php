<?php

use App\Actions\Quizzes\ManageQuiz;
use App\Enums\LanguageAspect;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Enums\ReadingDisplay;
use App\Enums\ResultVisibility;
use App\Http\Requests\QuizRequest;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Models\Quiz;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Quiz & ujian')] class extends Component {
    use WithPagination;

    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $type = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function classrooms()
    {
        $user = auth()->user();

        return Classroom::query()->active()->with('language')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->taughtBy($user))
            ->orderBy('name')->get();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'classroom_id' => $this->classroom ?? $this->classrooms()->first()?->id,
            'learning_session_id' => '',
            'type' => QuizType::Quiz->value,
            'title' => '',
            'instructions' => '',
            'aspect' => LanguageAspect::Vocabulary->value,
            'opens_at' => now()->addHour()->startOfHour()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addDays(2)->setTime(22, 0)->format('Y-m-d\TH:i'),
            'duration_minutes' => 20,
            'max_attempts' => 1,
            'passing_score' => 70,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'result_visibility' => ResultVisibility::Immediately->value,
            'reading_display' => ReadingDisplay::Ruby->value,
            'score_scale' => 100,
        ];
    }

    public function updatedFormType(string $value): void
    {
        // Pretest/posttest: hasil tidak langsung tampil, satu kali kesempatan.
        if (in_array($value, ['pretest', 'posttest'], true)) {
            $this->form['result_visibility'] = ResultVisibility::AfterClose->value;
            $this->form['max_attempts'] = 1;
        }
        if ($value === 'tryout') {
            $this->form['score_scale'] = 200;
            $this->form['duration_minutes'] = 40;
            $this->form['reading_display'] = ReadingDisplay::Hidden->value;
        }
    }

    public function create(): void
    {
        $this->authorize('create', Quiz::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $quiz = Quiz::findOrFail($id);
        $this->authorize('update', $quiz);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = [
            'classroom_id' => $quiz->classroom_id,
            'learning_session_id' => $quiz->learning_session_id ?? '',
            'type' => $quiz->type->value,
            'title' => $quiz->title,
            'instructions' => $quiz->instructions ?? '',
            'aspect' => $quiz->aspect->value,
            'opens_at' => $quiz->opens_at?->format('Y-m-d\TH:i') ?? '',
            'closes_at' => $quiz->closes_at?->format('Y-m-d\TH:i') ?? '',
            'duration_minutes' => $quiz->duration_minutes,
            'max_attempts' => $quiz->max_attempts,
            'passing_score' => $quiz->passing_score,
            'shuffle_questions' => $quiz->shuffle_questions,
            'shuffle_options' => $quiz->shuffle_options,
            'result_visibility' => $quiz->result_visibility->value,
            'reading_display' => $quiz->reading_display->value,
            'score_scale' => $quiz->score_scale,
        ];
        $this->showForm = true;
    }

    public function save(ManageQuiz $manage): void
    {
        $this->resetErrorBag();
        $quiz = $this->editingId ? Quiz::findOrFail($this->editingId) : null;
        $this->authorize($quiz ? 'update' : 'create', $quiz ?? Quiz::class);

        $validator = Validator::make($this->form, QuizRequest::rulesFor(), [], ['classroom_id' => 'kelas', 'title' => 'judul', 'opens_at' => 'waktu buka', 'closes_at' => 'waktu tutup', 'duration_minutes' => 'durasi', 'max_attempts' => 'jumlah percobaan', 'passing_score' => 'nilai lulus']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $classroom = $this->classrooms()->firstWhere('id', $this->form['classroom_id']);
        abort_unless($classroom, 403);

        try {
            $saved = $manage->save($classroom, auth()->user(), $validator->validated(), $quiz);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.learning_session_id', $e->getMessage());

            return;
        }

        $this->showForm = false;

        if (! $quiz) {
            $this->redirectRoute('teach.quizzes.show', ['quiz' => $saved, 'tab' => 'soal'], navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: 'Pengaturan quiz tersimpan.');
    }

    public function with(): array
    {
        $classrooms = $this->classrooms();

        return [
            'quizzes' => Quiz::query()
                ->with('classroom')
                ->withCount(['questions', 'attempts as finished_count' => fn ($q) => $q->where('status', '!=', 'in_progress'), 'attempts as pending_count' => fn ($q) => $q->where('status', 'needs_grading')])
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->when($this->classroom, fn ($q) => $q->where('classroom_id', $this->classroom))
                ->when($this->type, fn ($q) => $q->where('type', $this->type))
                ->latest()
                ->paginate(15),
            'classrooms' => $classrooms,
            'sessions' => filled($this->form['classroom_id'] ?? null) ? LearningSession::query()->where('classroom_id', $this->form['classroom_id'])->orderByDesc('date')->limit(40)->get() : collect(),
            'types' => QuizType::cases(),
            'aspects' => LanguageAspect::cases(),
            'visibilities' => ResultVisibility::cases(),
            'displays' => ReadingDisplay::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Quiz & ujian" subtitle="Quiz, pretest, posttest, dan tryout. Kunci jawaban tidak pernah dikirim ke peserta sebelum dikumpulkan.">
        <x-slot:actions>
            <flux:button icon="rectangle-stack" :href="route('teach.questions.index')" wire:navigate>Bank soal</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="create">Quiz baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:select wire:model.live="classroom">
            <flux:select.option value="">Semua kelas saya</flux:select.option>
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="type">
            <flux:select.option value="">Semua jenis</flux:select.option>
            @foreach ($types as $t)
                <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="space-y-2">
        @forelse ($quizzes as $quiz)
            <x-card :href="route('teach.quizzes.show', $quiz)" wire:key="qz-{{ $quiz->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $quiz->classroom->label() }} · {{ $quiz->opens_at ? tanggal_jam($quiz->opens_at) : 'Tanpa jadwal' }}{{ $quiz->closes_at ? ' – '.tanggal_jam($quiz->closes_at) : '' }}</p>
                        <p class="font-display text-lg font-bold">{{ $quiz->title }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <x-status-badge :status="$quiz->type" />
                            <x-status-badge :status="$quiz->status" />
                            <span>{{ $quiz->questions_count }} soal · {{ $quiz->duration_minutes }} menit · {{ $quiz->finished_count }} mengerjakan</span>
                            @if ($quiz->pending_count)<flux:badge size="sm" color="amber">{{ $quiz->pending_count }} perlu dinilai</flux:badge>@endif
                        </div>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="cog-6-tooth" wire:click.prevent="edit({{ $quiz->id }})" aria-label="Pengaturan" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="pencil-square" title="Belum ada quiz" />
        @endforelse
    </div>
    <div class="mt-4">{{ $quizzes->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Pengaturan quiz' : 'Quiz baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="form.classroom_id" label="Kelas">
                    @foreach ($classrooms as $c)
                        <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.type" label="Jenis">
                    @foreach ($types as $t)
                        <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:input wire:model="form.title" label="Judul" />
            <flux:textarea wire:model="form.instructions" label="Petunjuk" rows="2" />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="form.learning_session_id" label="Tautkan ke sesi (opsional)">
                    <flux:select.option value="">– Tidak ditautkan –</flux:select.option>
                    @foreach ($sessions as $s)
                        <flux:select.option :value="$s->id">{{ tanggal($s->date, 'j M') }} · {{ \Illuminate\Support\Str::limit($s->topic, 40) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.aspect" label="Aspek">
                    @foreach ($aspects as $a)
                        <flux:select.option :value="$a->value">{{ $a->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.opens_at" type="datetime-local" label="Dibuka" />
                <flux:input wire:model="form.closes_at" type="datetime-local" label="Ditutup" />
                <flux:input wire:model="form.duration_minutes" type="number" min="1" label="Durasi (menit)" />
                <flux:input wire:model="form.max_attempts" type="number" min="1" max="10" label="Jumlah percobaan" />
                <flux:input wire:model="form.passing_score" type="number" min="0" max="100" label="Nilai lulus (%)" />
                <flux:input wire:model="form.score_scale" type="number" min="10" label="Skala skor" description="100 untuk quiz; tryout HSK 1–2 = 200." />
                <flux:select wire:model="form.result_visibility" label="Hasil ditampilkan">
                    @foreach ($visibilities as $v)
                        <flux:select.option :value="$v->value">{{ $v->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.reading_display" label="Bacaan (pinyin/romaji)">
                    @foreach ($displays as $d)
                        <flux:select.option :value="$d->value">{{ $d->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex flex-wrap gap-4">
                <flux:switch wire:model="form.shuffle_questions" label="Acak soal" />
                <flux:switch wire:model="form.shuffle_options" label="Acak opsi" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Simpan' : 'Lanjut pilih soal' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
