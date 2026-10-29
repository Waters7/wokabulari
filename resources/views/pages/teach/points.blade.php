<?php

use App\Actions\Gamification\AwardPointComponent;
use App\Actions\Gamification\ManagePointComponents;
use App\Enums\PointCategory;
use App\Enums\ReviewStatus;
use App\Http\Requests\PointComponentRequest;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\GamificationRule;
use App\Models\PointAward;
use App\Models\Quiz;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Poin peserta')] class extends Component {
    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    public ?int $componentId = null;

    /** @var list<int|string> */
    public array $participantIds = [];

    public ?int $assignmentId = null;

    public ?int $quizId = null;

    public string $note = '';

    public bool $showPropose = false;

    /** @var array<string, mixed> */
    public array $proposal = [];

    public function mount(): void
    {
        $this->authorize('viewAny', PointAward::class);
        $this->classroomId ??= $this->classrooms()->first()?->id;
        $this->resetProposal();
    }

    protected function classrooms()
    {
        $user = auth()->user();

        return Classroom::query()->active()
            ->when(! $user->can('gamification.manage'), fn ($q) => $q->taughtBy($user))
            ->orderBy('name')->get(['id', 'name']);
    }

    protected function resetProposal(): void
    {
        $this->proposal = ['name' => '', 'category' => PointCategory::Assignment->value, 'points' => 10, 'description' => ''];
    }

    public function updatedClassroomId(): void
    {
        $this->participantIds = [];
        $this->assignmentId = null;
        $this->quizId = null;
    }

    public function updatedComponentId(): void
    {
        $this->assignmentId = null;
        $this->quizId = null;
    }

    public function selectAll(): void
    {
        $classroom = Classroom::query()->find($this->classroomId);
        $this->participantIds = $classroom
            ? $classroom->participants()->where('users.is_active', true)->pluck('users.id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function award(AwardPointComponent $award): void
    {
        $this->resetErrorBag();
        $data = [
            'component_id' => $this->componentId, 'classroom_id' => $this->classroomId, 'participant_ids' => $this->participantIds,
            'assignment_id' => $this->assignmentId, 'quiz_id' => $this->quizId, 'note' => $this->note,
        ];

        $validator = Validator::make($data, PointComponentRequest::awardRules(), [], PointComponentRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($m, $k) => [match ($k) {'component_id' => 'componentId', 'classroom_id' => 'classroomId', 'assignment_id' => 'assignmentId', 'quiz_id' => 'quizId', default => str_starts_with($k, 'participant_ids') ? 'participantIds' : $k} => $m])->all());
        }

        $component = GamificationRule::query()->findOrFail($this->componentId);
        $classroom = Classroom::query()->findOrFail($this->classroomId);
        $this->authorize('award', $component);
        $this->authorize('create', [PointAward::class, $classroom]);

        try {
            ['awarded' => $count] = $award->handle($component, $classroom, $this->participantIds, auth()->user(), $this->note, $this->assignmentId, $this->quizId);
        } catch (\InvalidArgumentException $e) {
            $this->addError('participantIds', $e->getMessage());

            return;
        }

        $this->participantIds = [];
        $this->note = '';
        Flux::toast(variant: 'success', text: $component->pointsLabel().' poin "'.$component->name.'" diberikan ke '.$count.' peserta.');
    }

    public function openPropose(): void
    {
        $this->authorize('propose', GamificationRule::class);
        $this->resetErrorBag();
        $this->resetProposal();
        $this->showPropose = true;
    }

    public function propose(ManagePointComponents $manage): void
    {
        $this->authorize('propose', GamificationRule::class);
        $this->resetErrorBag();

        $validator = Validator::make($this->proposal, PointComponentRequest::proposalRules(), [], PointComponentRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['proposal.'.$k => $m])->all());
        }

        $manage->propose($validator->validated(), auth()->user());
        $this->showPropose = false;
        Flux::toast(variant: 'success', text: 'Pengajuan terkirim. Komponen bisa diberikan setelah disetujui admin.');
    }

    public function with(): array
    {
        $classrooms = $this->classrooms();
        $classroom = $classrooms->firstWhere('id', $this->classroomId);
        $component = $this->componentId ? GamificationRule::query()->find($this->componentId) : null;

        return [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'components' => GamificationRule::query()->awardable()->orderBy('category')->orderBy('name')->get(),
            'selected' => $component,
            'participants' => $classroom ? Classroom::query()->find($classroom->id)->participants()->where('users.is_active', true)->orderBy('name')->get(['users.id', 'users.name']) : collect(),
            'assignments' => $classroom && $component?->category === PointCategory::Assignment
                ? Assignment::query()->where('classroom_id', $classroom->id)->latest('due_at')->get(['id', 'title']) : collect(),
            'quizzes' => $classroom && $component?->category === PointCategory::Quiz
                ? Quiz::query()->where('classroom_id', $classroom->id)->latest()->get(['id', 'title']) : collect(),
            'proposals' => GamificationRule::query()->custom()->where('proposed_by', auth()->id())->latest()->limit(10)->get(),
            'recent' => PointAward::query()->with(['component', 'classroom', 'awarder'])->withCount('transactions')
                ->whereIn('classroom_id', $classrooms->pluck('id'))->latest()->limit(10)->get(),
            'categories' => PointCategory::forCustom(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header title="Poin peserta" subtitle="Beri poin tambahan untuk tugas, quiz, atau keaktifan peserta kelas. Komponen baru diajukan dulu ke admin.">
        <x-slot:actions>
            @can('propose', GamificationRule::class)
                <flux:button icon="light-bulb" wire:click="openPropose">Ajukan komponen</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($classrooms->isEmpty())
        <x-empty-state icon="building-library" title="Belum ada kelas aktif" description="Poin tambahan diberikan ke peserta kelas yang Anda ajar." />
    @else
        <x-card>
            <form wire:submit="award" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:select wire:model.live="classroomId" label="Kelas">
                        @foreach ($classrooms as $option)
                            <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="componentId" label="Komponen" placeholder="Pilih komponen">
                        @foreach ($components as $option)
                            <flux:select.option :value="$option->id">{{ $option->name }} ({{ $option->pointsLabel() }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:error name="componentId" />
                @if ($components->isEmpty())
                    <flux:text class="text-sm">Belum ada komponen tambahan yang disetujui. Ajukan komponen, lalu tunggu persetujuan admin.</flux:text>
                @endif
                @if ($selected?->description)
                    <flux:text class="text-sm">{{ $selected->description }}</flux:text>
                @endif

                @if ($assignments->isNotEmpty())
                    <flux:select wire:model="assignmentId" label="Terkait tugas (opsional)" placeholder="Tanpa tugas tertentu">
                        @foreach ($assignments as $option)
                            <flux:select.option :value="$option->id">{{ $option->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif ($quizzes->isNotEmpty())
                    <flux:select wire:model="quizId" label="Terkait quiz (opsional)" placeholder="Tanpa quiz tertentu">
                        @foreach ($quizzes as $option)
                            <flux:select.option :value="$option->id">{{ $option->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium">Peserta <span class="text-hutan/50 dark:text-santan/50">({{ count($participantIds) }} dipilih)</span></span>
                        <flux:button size="xs" variant="ghost" wire:click="selectAll">Pilih semua</flux:button>
                    </div>
                    <div class="max-h-72 divide-y divide-zinc-100 overflow-y-auto rounded-xl ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:ring-hutan-800">
                        @forelse ($participants as $participant)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 px-3 py-2 text-sm" wire:key="pp-{{ $participant->id }}">
                                <input type="checkbox" wire:model.live="participantIds" value="{{ $participant->id }}" class="size-4 rounded border-zinc-300 text-woka focus:ring-woka">
                                <span class="min-w-0 truncate">{{ $participant->name }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-4 text-sm text-hutan/60 dark:text-santan/60">Belum ada peserta di kelas ini.</p>
                        @endforelse
                    </div>
                    <flux:error name="participantIds" />
                </div>

                <flux:input wire:model="note" label="Catatan (opsional)" placeholder="Presentasi tentang keluarga" />
                <flux:button type="submit" variant="primary" icon="sparkles" class="w-full">Beri poin</flux:button>
            </form>
        </x-card>
    @endif

    @if ($proposals->isNotEmpty())
        <section>
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Pengajuan saya</h2>
            <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
                @foreach ($proposals as $proposalItem)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="pr-{{ $proposalItem->id }}">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $proposalItem->name }} <span class="font-normal text-hutan/60 dark:text-santan/60">{{ $proposalItem->pointsLabel() }}</span></p>
                            <p class="truncate text-xs text-hutan/60 dark:text-santan/60">
                                {{ $proposalItem->category->label() }}
                                @if ($proposalItem->status === ReviewStatus::Rejected && $proposalItem->review_note) · {{ $proposalItem->review_note }} @endif
                            </p>
                        </div>
                        <x-status-badge :status="$proposalItem->status" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($recent->isNotEmpty())
        <section>
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Riwayat pemberian</h2>
            <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
                @foreach ($recent as $item)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="ra-{{ $item->id }}">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $item->component->name }}</p>
                            <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $item->classroom?->name }} · {{ $item->transactions_count }} peserta · {{ $item->awarder?->name }} · {{ tanggal($item->created_at, 'j M') }}</p>
                        </div>
                        <span @class(['shrink-0 font-bold', 'text-woka' => $item->points > 0, 'text-rica' => $item->points < 0])>{{ $item->points > 0 ? '+' : '' }}{{ $item->points }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <flux:modal wire:model.self="showPropose" class="w-full max-w-md">
        <form wire:submit="propose" class="space-y-4">
            <div>
                <flux:heading size="lg">Ajukan komponen poin</flux:heading>
                <flux:text class="mt-1">Mis. "Tugas proyek video" atau "Juara quiz kosakata". Admin meninjau poin sebelum komponen bisa diberikan.</flux:text>
            </div>
            <flux:input wire:model="proposal.name" label="Nama komponen" placeholder="Juara quiz kosakata" />
            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="proposal.category" label="Kategori">
                    @foreach ($categories as $category)
                        <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="proposal.points" type="number" min="1" max="100" label="Usulan poin" />
            </div>
            <flux:textarea wire:model="proposal.description" label="Kapan diberikan" rows="3" placeholder="Untuk 3 peserta dengan skor tertinggi di quiz kosakata mingguan." />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim pengajuan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
