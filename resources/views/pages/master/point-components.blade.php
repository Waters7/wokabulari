<?php

use App\Actions\Gamification\ManagePointComponents;
use App\Enums\PointCategory;
use App\Enums\ReviewStatus;
use App\Http\Requests\GamificationRuleRequest;
use App\Http\Requests\PointComponentRequest;
use App\Models\GamificationRule;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Komponen poin')] class extends Component {
    /** @var array<int, array{points: int, max_per_day: ?int, is_active: bool}> Aturan otomatis (sunting di tempat) */
    public array $rules = [];

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<int, int|string> Poin yang disetujui per pengajuan */
    public array $approvePoints = [];

    public bool $showReject = false;

    public ?int $rejectingId = null;

    public string $rejectNote = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GamificationRule::class);
        $this->loadRules();
        $this->resetForm();
    }

    protected function loadRules(): void
    {
        $this->rules = GamificationRule::query()->where('is_custom', false)->orderBy('id')->get()
            ->mapWithKeys(fn (GamificationRule $r) => [$r->id => ['points' => $r->points, 'max_per_day' => $r->max_per_day, 'is_active' => $r->is_active]])->all();
        $this->approvePoints = GamificationRule::query()->custom()->where('status', ReviewStatus::Pending)->pluck('points', 'id')->all();
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'category' => PointCategory::Engagement->value, 'points' => 10, 'max_per_day' => null, 'description' => '', 'is_active' => true];
    }

    public function saveRule(int $id, ManagePointComponents $manage): void
    {
        $rule = GamificationRule::query()->where('is_custom', false)->findOrFail($id);
        $this->authorize('update', $rule);
        $this->resetErrorBag("rules.{$id}.*");
        $data = $this->rules[$id] ?? [];
        $data['max_per_day'] = filled($data['max_per_day'] ?? null) ? $data['max_per_day'] : null;

        $validator = Validator::make($data, GamificationRuleRequest::rulesFor(), [], ['points' => 'poin', 'max_per_day' => 'maks. per hari']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["rules.{$id}.{$k}" => $m])->all());
        }

        $manage->update($rule, $validator->validated());
        Flux::toast(variant: 'success', text: 'Komponen "'.$rule->name.'" disimpan.');
    }

    public function create(): void
    {
        $this->authorize('create', GamificationRule::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $component = GamificationRule::query()->custom()->findOrFail($id);
        $this->authorize('update', $component);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = [
            'name' => $component->name, 'category' => $component->category->value, 'points' => $component->points,
            'max_per_day' => $component->max_per_day, 'description' => (string) $component->description, 'is_active' => $component->is_active,
        ];
        $this->showForm = true;
    }

    public function save(ManagePointComponents $manage): void
    {
        $component = $this->editingId ? GamificationRule::query()->custom()->findOrFail($this->editingId) : null;
        $component ? $this->authorize('update', $component) : $this->authorize('create', GamificationRule::class);
        $this->resetErrorBag();

        $data = $this->form;
        $data['max_per_day'] = filled($data['max_per_day'] ?? null) ? $data['max_per_day'] : null;
        $validator = Validator::make($data, PointComponentRequest::rulesFor(), [], PointComponentRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $component ? $manage->update($component, $validator->validated()) : $manage->create($validator->validated(), auth()->user());
        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Komponen poin tersimpan.');
    }

    public function delete(int $id, ManagePointComponents $manage): void
    {
        $component = GamificationRule::query()->custom()->findOrFail($id);
        $this->authorize('delete', $component);
        $deleted = $manage->delete($component);
        Flux::toast(text: $deleted ? 'Komponen dihapus.' : 'Komponen sudah pernah diberikan, jadi dinonaktifkan agar riwayat poin tetap utuh.');
    }

    public function approve(int $id, ManagePointComponents $manage): void
    {
        $component = GamificationRule::query()->custom()->findOrFail($id);
        $this->authorize('review', $component);
        $this->resetErrorBag("approvePoints.{$id}");

        $points = $this->approvePoints[$id] ?? $component->points;
        $validator = Validator::make(['points' => $points], ['points' => ['required', 'integer', 'between:1,200']], [], ['points' => 'poin']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(["approvePoints.{$id}" => $validator->errors()->all()]);
        }

        $manage->approve($component, auth()->user(), (int) $points);
        $this->loadRules();
        Flux::toast(variant: 'success', text: '"'.$component->name.'" disetujui; pengajar sudah bisa memberikannya.');
    }

    public function openReject(int $id): void
    {
        $this->authorize('review', GamificationRule::query()->custom()->findOrFail($id));
        $this->resetErrorBag('rejectNote');
        $this->rejectingId = $id;
        $this->rejectNote = '';
        $this->showReject = true;
    }

    public function reject(ManagePointComponents $manage): void
    {
        $component = GamificationRule::query()->custom()->findOrFail($this->rejectingId);
        $this->authorize('review', $component);
        $this->resetErrorBag('rejectNote');

        if (mb_strlen(trim($this->rejectNote)) < 5) {
            $this->addError('rejectNote', 'Tulis alasan penolakan (minimal 5 karakter).');

            return;
        }

        $manage->reject($component, auth()->user(), $this->rejectNote);
        $this->showReject = false;
        $this->rejectingId = null;
        $this->loadRules();
        Flux::toast(text: 'Pengajuan ditolak; pengajar diberi tahu alasannya.');
    }

    public function with(): array
    {
        $all = GamificationRule::query()->with(['proposer', 'reviewer'])->withCount('awards')->orderBy('id')->get();

        return [
            'pending' => $all->filter(fn (GamificationRule $r) => $r->is_custom && $r->status === ReviewStatus::Pending)->values(),
            'groups' => collect(PointCategory::cases())
                ->map(fn (PointCategory $category) => [
                    'category' => $category,
                    'items' => $all->filter(fn (GamificationRule $r) => $r->category === $category && $r->status !== ReviewStatus::Pending)->values(),
                ])
                ->filter(fn (array $group) => $group['items']->isNotEmpty())
                ->values(),
            'categories' => PointCategory::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Komponen poin" subtitle="Poin otomatis dari aktivitas peserta dan komponen tambahan yang diberikan pengajar. Perubahan berlaku untuk aktivitas berikutnya.">
        <x-slot:actions>
            <flux:button icon="sparkles" :href="route('manage.gamification')" wire:navigate>Level & lencana</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah komponen</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($pending->isNotEmpty())
        <h2 class="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">
            Pengajuan pengajar <flux:badge size="sm" color="amber">{{ $pending->count() }}</flux:badge>
        </h2>
        <div class="mb-6 space-y-2" data-test="pending-components">
            @foreach ($pending as $proposal)
                <x-card wire:key="pc-{{ $proposal->id }}">
                    <div class="flex items-start gap-3">
                        <flux:icon :name="$proposal->icon()" class="mt-0.5 size-5 shrink-0 text-kunyit-700 dark:text-kunyit" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $proposal->name }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $proposal->category->label() }} · diajukan {{ $proposal->proposer?->name }} · {{ tanggal($proposal->created_at, 'j M') }}</p>
                            @if ($proposal->description)
                                <p class="mt-1 text-sm">{{ $proposal->description }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <flux:input wire:model="approvePoints.{{ $proposal->id }}" type="number" size="sm" label="Poin" class="w-24" />
                        <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $proposal->id }})">Setujui</flux:button>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="openReject({{ $proposal->id }})">Tolak</flux:button>
                    </div>
                    <flux:error name="approvePoints.{{ $proposal->id }}" />
                </x-card>
            @endforeach
        </div>
    @endif

    @foreach ($groups as $group)
        <h2 class="mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60" wire:key="pg-{{ $group['category']->value }}">
            <flux:icon :name="$group['category']->icon()" class="size-4" /> {{ $group['category']->label() }}
        </h2>
        <x-card :padding="false" class="mb-6 divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($group['items'] as $rule)
                <div class="px-4 py-3" wire:key="rule-{{ $rule->id }}">
                    @if ($rule->is_custom)
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold">
                                    {{ $rule->name }}
                                    <span @class(['ms-1 font-bold', 'text-woka' => $rule->points > 0, 'text-rica' => $rule->points < 0])>{{ $rule->pointsLabel() }}</span>
                                </p>
                                <p class="text-xs text-hutan/60 dark:text-santan/60">
                                    Komponen tambahan · diberikan manual
                                    @if ($rule->proposer) · usulan {{ $rule->proposer->name }} @endif
                                    · {{ $rule->awards_count }}× diberikan
                                    @if ($rule->status === ReviewStatus::Rejected) · <span class="text-red-600">ditolak</span> @elseif (! $rule->is_active) · <span class="text-red-600">nonaktif</span> @endif
                                </p>
                                @if ($rule->description)
                                    <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">{{ $rule->description }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $rule->id }})" aria-label="Ubah" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $rule->id }})" wire:confirm="Hapus komponen ini? Bila sudah pernah diberikan, komponen hanya dinonaktifkan." aria-label="Hapus" />
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <flux:icon :name="$rule->icon()" class="size-5 shrink-0 text-woka" />
                                <p class="truncate font-semibold">{{ $rule->name }}</p>
                            </div>
                            <flux:switch wire:model="rules.{{ $rule->id }}.is_active" aria-label="Aktif" />
                        </div>
                        <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Otomatis{{ $rule->description ? ' · '.$rule->description : '' }}</p>
                        <div class="mt-2 flex items-end gap-2">
                            <flux:input wire:model="rules.{{ $rule->id }}.points" type="number" size="sm" label="Poin" class="w-24" />
                            <flux:input wire:model="rules.{{ $rule->id }}.max_per_day" type="number" size="sm" label="Maks./hari" placeholder="–" class="w-24" />
                            <flux:button size="sm" wire:click="saveRule({{ $rule->id }})" class="ms-auto">Simpan</flux:button>
                        </div>
                        <flux:error name="rules.{{ $rule->id }}.points" />
                        <flux:error name="rules.{{ $rule->id }}.max_per_day" />
                    @endif
                </div>
            @endforeach
        </x-card>
    @endforeach

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Ubah komponen' : 'Komponen poin tambahan' }}</flux:heading>
                <flux:text class="mt-1">Diberikan manual oleh pengajar/admin ke peserta, mis. keaktifan, presentasi terbaik, tugas proyek.</flux:text>
            </div>
            <flux:input wire:model="form.name" label="Nama komponen" placeholder="Presentasi terbaik" />
            <flux:select wire:model="form.category" label="Kategori">
                @foreach ($categories as $category)
                    <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.points" type="number" label="Poin" description="Negatif untuk pengurang." />
                <flux:input wire:model="form.max_per_day" type="number" label="Maks./hari/peserta" placeholder="–" />
            </div>
            <flux:textarea wire:model="form.description" label="Keterangan" rows="2" placeholder="Kapan komponen ini diberikan" />
            <flux:switch wire:model="form.is_active" label="Aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showReject" class="w-full max-w-sm">
        <form wire:submit="reject" class="space-y-4">
            <flux:heading size="lg">Tolak pengajuan</flux:heading>
            <flux:textarea wire:model="rejectNote" label="Alasan" rows="3" placeholder="Sudah tercakup di komponen Keaktifan kelas" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Tolak</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
