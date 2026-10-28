<?php

use App\Actions\Assignments\SaveAssignment;
use App\Enums\AssignmentStatus;
use App\Enums\LanguageAspect;
use App\Enums\SubmissionType;
use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\LearningSession;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Tugas')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $status = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<mixed> */
    public array $attachments = [];

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
            'title' => '',
            'instructions' => '',
            'submission_type' => SubmissionType::Mixed->value,
            'aspect' => LanguageAspect::Writing->value,
            'due_at' => now()->addDays(2)->setTime(21, 0)->format('Y-m-d\TH:i'),
            'max_score' => 100,
            'allow_late' => true,
            'late_penalty_percent' => 10,
            'allow_resubmit' => false,
        ];
        $this->attachments = [];
    }

    public function create(): void
    {
        $this->authorize('create', Assignment::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('update', $assignment);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = [
            'classroom_id' => $assignment->classroom_id,
            'learning_session_id' => $assignment->learning_session_id ?? '',
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'submission_type' => $assignment->submission_type->value,
            'aspect' => $assignment->aspect->value,
            'due_at' => $assignment->due_at->format('Y-m-d\TH:i'),
            'max_score' => $assignment->max_score,
            'allow_late' => $assignment->allow_late,
            'late_penalty_percent' => $assignment->late_penalty_percent,
            'allow_resubmit' => $assignment->allow_resubmit,
        ];
        $this->attachments = [];
        $this->showForm = true;
    }

    public function save(SaveAssignment $save, bool $publish = false): void
    {
        $this->resetErrorBag();
        $assignment = $this->editingId ? Assignment::findOrFail($this->editingId) : null;
        $this->authorize($assignment ? 'update' : 'create', $assignment ?? Assignment::class);

        $validator = Validator::make($this->form + ['attachments' => $this->attachments], AssignmentRequest::rulesFor(), [], [
            'classroom_id' => 'kelas', 'title' => 'judul', 'instructions' => 'instruksi', 'due_at' => 'tenggat', 'max_score' => 'skor maksimal',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [str_starts_with($k, 'attachments') ? $k : 'form.'.$k => $m])->all());
        }

        $classroom = $this->classrooms()->firstWhere('id', $this->form['classroom_id']);
        abort_unless($classroom, 403);

        try {
            $saved = $save->save($classroom, auth()->user(), $validator->validated(), $assignment, $this->attachments);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.learning_session_id', $e->getMessage());

            return;
        }

        if ($publish) {
            $save->publish($saved);
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: $publish ? 'Tugas diterbitkan. Peserta mendapat notifikasi.' : 'Tugas tersimpan.');
    }

    public function publish(int $id, SaveAssignment $save): void
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('update', $assignment);
        $save->publish($assignment);
        Flux::toast(variant: 'success', text: 'Tugas diterbitkan.');
    }

    public function close(int $id, SaveAssignment $save): void
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('update', $assignment);
        $save->close($assignment);
        Flux::toast(text: 'Tugas ditutup.');
    }

    public function delete(int $id): void
    {
        $assignment = Assignment::findOrFail($id);
        $this->authorize('delete', $assignment);
        $assignment->delete();
        Flux::toast(text: 'Tugas dihapus.');
    }

    public function with(): array
    {
        $classrooms = $this->classrooms();

        return [
            'assignments' => Assignment::query()
                ->with('classroom')
                ->withCount(['submissions as submitted_count', 'submissions as ungraded_count' => fn ($q) => $q->whereIn('status', ['submitted', 'late'])])
                ->whereIn('classroom_id', $classrooms->pluck('id'))
                ->when($this->classroom, fn ($q) => $q->where('classroom_id', $this->classroom))
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->orderByDesc('due_at')
                ->paginate(15),
            'classrooms' => $classrooms,
            'sessions' => filled($this->form['classroom_id'] ?? null)
                ? LearningSession::query()->where('classroom_id', $this->form['classroom_id'])->where('status', '!=', 'cancelled')->orderByDesc('date')->limit(40)->get()
                : collect(),
            'types' => SubmissionType::cases(),
            'aspects' => LanguageAspect::cases(),
            'statuses' => AssignmentStatus::cases(),
            'participantCounts' => $classrooms->mapWithKeys(fn ($c) => [$c->id => $c->participants()->count()]),
        ];
    }
}; ?>

<div>
    <x-page-header title="Tugas" subtitle="Buat tugas harian, terima pengumpulan dari HP, lalu nilai dengan cepat.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Tugas baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:select wire:model.live="classroom">
            <flux:select.option value="">Semua kelas saya</flux:select.option>
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="">Semua status</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="space-y-2">
        @forelse ($assignments as $assignment)
            <x-card wire:key="asg-{{ $assignment->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <a href="{{ route('teach.assignments.show', $assignment) }}" wire:navigate class="min-w-0 flex-1">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $assignment->classroom->label() }} · tenggat {{ tanggal_jam($assignment->due_at) }}</p>
                        <p class="font-display text-lg font-bold hover:text-woka">{{ $assignment->title }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <x-status-badge :status="$assignment->status" />
                            <x-status-badge :status="$assignment->submission_type" />
                            <span>{{ $assignment->submitted_count }}/{{ $participantCounts[$assignment->classroom_id] ?? 0 }} mengumpulkan</span>
                            @if ($assignment->ungraded_count)
                                <flux:badge size="sm" color="amber">{{ $assignment->ungraded_count }} perlu dinilai</flux:badge>
                            @endif
                        </div>
                    </a>
                    <div class="flex shrink-0 items-center gap-1">
                        @if ($assignment->status === AssignmentStatus::Draft)
                            <flux:button size="sm" variant="primary" icon="paper-airplane" wire:click="publish({{ $assignment->id }})">Terbitkan</flux:button>
                        @endif
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Aksi" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $assignment->id }})">Ubah</flux:menu.item>
                                @if ($assignment->status === AssignmentStatus::Published)
                                    <flux:menu.item icon="lock-closed" wire:click="close({{ $assignment->id }})">Tutup pengumpulan</flux:menu.item>
                                @endif
                                @if ($assignment->submitted_count === 0)
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $assignment->id }})" wire:confirm="Hapus tugas ini?">Hapus</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="clipboard-document-check" title="Belum ada tugas" />
        @endforelse
    </div>
    <div class="mt-4">{{ $assignments->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah tugas' : 'Tugas baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="form.classroom_id" label="Kelas">
                    @foreach ($classrooms as $c)
                        <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.learning_session_id" label="Tautkan ke sesi (opsional)" description="Nilai otomatis masuk komponen penilaian sesi.">
                    <flux:select.option value="">– Tidak ditautkan –</flux:select.option>
                    @foreach ($sessions as $s)
                        <flux:select.option :value="$s->id">{{ tanggal($s->date, 'j M') }} · {{ \Illuminate\Support\Str::limit($s->topic, 40) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:input wire:model="form.title" label="Judul" />
            <flux:textarea wire:model="form.instructions" label="Instruksi" rows="4" />
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="form.submission_type" label="Jenis pengumpulan">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.aspect" label="Aspek">
                    @foreach ($aspects as $aspect)
                        <flux:select.option :value="$aspect->value">{{ $aspect->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.due_at" type="datetime-local" label="Tenggat (WITA)" />
                <flux:input wire:model="form.max_score" type="number" min="1" label="Skor maksimal" />
            </div>
            <div class="grid gap-3 rounded-xl bg-santan p-3 sm:grid-cols-2 dark:bg-hutan-950">
                <flux:switch wire:model.live="form.allow_late" label="Izinkan terlambat" />
                @if ($form['allow_late'])
                    <flux:input wire:model="form.late_penalty_percent" type="number" min="0" max="100" label="Potongan nilai terlambat (%)" />
                @endif
                <flux:switch wire:model="form.allow_resubmit" label="Izinkan kumpul ulang sebelum dinilai" />
            </div>
            <div>
                <flux:input type="file" wire:model="attachments" multiple label="Lampiran (opsional)" />
                <div wire:loading wire:target="attachments" class="mt-1 text-xs text-woka">Mengunggah…</div>
                <flux:error name="attachments.0" />
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit">Simpan</flux:button>
                @unless ($editingId)
                    <flux:button variant="primary" wire:click.prevent="save(true)" icon="paper-airplane">Simpan & terbitkan</flux:button>
                @endunless
            </div>
        </form>
    </flux:modal>
</div>
