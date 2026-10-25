<?php

use App\Actions\Assessments\ManageComponents;
use App\Actions\Assessments\SaveSessionScore;
use App\Enums\LanguageAspect;
use App\Http\Requests\AssessmentComponentRequest;
use App\Models\AssessmentComponentTemplate;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\SessionScoreCalculator;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public LearningSession $session;

    public bool $showComponent = false;

    public ?int $editingComponentId = null;

    /** @var array<string, mixed> */
    public array $component = [];

    public bool $saveAsTemplate = false;

    public bool $showTemplates = false;

    /** @var list<int> */
    public array $selectedTemplates = [];

    public function mount(): void
    {
        $this->resetComponent();
    }

    protected function resetComponent(): void
    {
        $this->component = ['name' => '', 'aspect' => LanguageAspect::Speaking->value, 'weight' => 25, 'max_score' => 100];
        $this->saveAsTemplate = false;
    }

    protected function canAssess(): bool
    {
        return auth()->user()->can('assess', $this->session);
    }

    public function createComponent(): void
    {
        $this->authorize('assess', $this->session);
        $this->resetErrorBag();
        $this->editingComponentId = null;
        $this->resetComponent();
        $this->showComponent = true;
    }

    public function editComponent(int $id): void
    {
        $this->authorize('assess', $this->session);
        $component = $this->session->components()->findOrFail($id);
        $this->resetErrorBag();
        $this->editingComponentId = $id;
        $this->component = ['name' => $component->name, 'aspect' => $component->aspect->value, 'weight' => $component->weight, 'max_score' => $component->max_score];
        $this->showComponent = true;
    }

    public function saveComponent(ManageComponents $components): void
    {
        $this->authorize('assess', $this->session);
        $this->resetErrorBag();

        $validator = Validator::make($this->component, AssessmentComponentRequest::rulesFor(), [], ['name' => 'nama komponen', 'aspect' => 'aspek', 'weight' => 'bobot', 'max_score' => 'skor maksimal']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['component.'.$k => $m])->all());
        }

        try {
            $saved = $this->editingComponentId
                ? $components->update($this->session->components()->findOrFail($this->editingComponentId), $validator->validated())
                : $components->add($this->session, $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->addError('component.max_score', $e->getMessage());

            return;
        }

        if ($this->saveAsTemplate) {
            $components->saveAsTemplate($saved, auth()->user());
        }

        $this->showComponent = false;
        Flux::toast(variant: 'success', text: 'Komponen tersimpan.');
    }

    public function deleteComponent(int $id, ManageComponents $components): void
    {
        $this->authorize('assess', $this->session);

        try {
            $components->delete($this->session->components()->findOrFail($id));
            Flux::toast(text: 'Komponen dihapus.');
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function applyTemplates(ManageComponents $components): void
    {
        $this->authorize('assess', $this->session);
        $count = $components->applyTemplates($this->session, auth()->user(), $this->selectedTemplates);
        $this->showTemplates = false;
        $this->selectedTemplates = [];
        Flux::toast(variant: 'success', text: "{$count} komponen ditambahkan dari template.");
    }

    public function deleteTemplate(int $id): void
    {
        AssessmentComponentTemplate::query()->where('teacher_id', auth()->id())->whereKey($id)->delete();
    }

    /**
     * Simpan satu sel skor. Tidak merender ulang tabel; hasil dikembalikan ke Alpine.
     *
     * @return array{ok: bool, message?: string, final?: float|null}
     */
    public function saveScore(int $componentId, int $userId, ?string $value, SaveSessionScore $save, SessionScoreCalculator $calculator): array
    {
        $this->skipRender();

        if (! $this->canAssess()) {
            return ['ok' => false, 'message' => 'Tidak berwenang.'];
        }

        $component = $this->session->components()->findOrFail($componentId);

        try {
            $save->handle($component, User::findOrFail($userId), $value);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $components = $this->session->components()->with(['scores' => fn ($q) => $q->where('user_id', $userId)])->get();

        return ['ok' => true, 'final' => $calculator->forUser($components, $userId)];
    }

    public function with(): array
    {
        $calculator = app(SessionScoreCalculator::class);
        $components = $this->session->components()->with('scores')->get();
        $participants = $this->session->classroom->participants()->orderBy('name')->get();

        return [
            'components' => $components,
            'participants' => $participants,
            'scores' => $components->mapWithKeys(fn ($c) => [$c->id => $c->scores->pluck('score', 'user_id')]),
            'finals' => $calculator->forSession($this->session, $components),
            'totalWeight' => $components->sum('weight'),
            'templates' => AssessmentComponentTemplate::query()->where('teacher_id', auth()->id())->orderBy('name')->get(),
            'aspects' => LanguageAspect::cases(),
            'canAssess' => $this->canAssess(),
        ];
    }
}; ?>

<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-hutan/70 dark:text-santan/70">
            Nilai akhir = rata-rata tertimbang (0–100). Total bobot: <span class="font-semibold">{{ angka($totalWeight) }}</span>
        </p>
        @if ($canAssess)
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="rectangle-stack" wire:click="$set('showTemplates', true)">Pakai template</flux:button>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="createComponent">Komponen</flux:button>
            </div>
        @endif
    </div>

    @if ($components->isEmpty())
        <x-empty-state icon="adjustments-horizontal" title="Belum ada komponen penilaian"
                       description="Contoh: Speaking Practice (40%), Role Play (30%), Partisipasi (30%). Skor quiz & tugas yang ditautkan ke sesi masuk otomatis." />
    @else
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
            @foreach ($components as $c)
                <div class="min-w-44 rounded-xl bg-white p-3 ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800" wire:key="comp-{{ $c->id }}">
                    <div class="flex items-start justify-between gap-1">
                        <p class="text-sm font-semibold leading-tight">{{ $c->name }}</p>
                        @if ($canAssess && $c->isEditable())
                            <div class="-me-2 -mt-1 flex">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editComponent({{ $c->id }})" aria-label="Ubah" />
                                <x-confirm-button action="deleteComponent({{ $c->id }})" size="xs" title="Hapus komponen {{ $c->name }}?" message="Semua skor komponen ini ikut terhapus." />
                            </div>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <x-status-badge :status="$c->aspect" />
                        @unless ($c->isEditable())
                            <x-status-badge :status="$c->source" />
                        @endunless
                    </div>
                    <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Bobot {{ angka($c->weight) }} · maks {{ angka($c->max_score) }}</p>
                </div>
            @endforeach
        </div>

        <div class="table-scroll" x-data="{
                status: {},
                finals: @js((object) $finals),
                move(el, dir) {
                    const row = +el.dataset.row + dir, col = el.dataset.col;
                    const next = this.$root.querySelector(`[data-row='${row}'][data-col='${col}']`);
                    if (next) { next.focus(); next.select(); }
                },
                async save(el) {
                    const key = el.dataset.c + '-' + el.dataset.u;
                    if (el.value === el.dataset.saved) return;
                    this.status[key] = 'saving';
                    const result = await $wire.saveScore(+el.dataset.c, +el.dataset.u, el.value);
                    if (result.ok) {
                        el.dataset.saved = el.value;
                        this.status[key] = 'ok';
                        this.finals[el.dataset.u] = result.final;
                    } else {
                        this.status[key] = 'error';
                        el.title = result.message;
                        $flux.toast({ variant: 'danger', text: result.message });
                    }
                },
             }">
            <table>
                <thead>
                    <tr>
                        <th class="sticky start-0 z-10 bg-santan dark:bg-hutan-800">Peserta</th>
                        @foreach ($components as $c)
                            <th class="text-center">{{ \Illuminate\Support\Str::limit($c->name, 18) }}<span class="block font-normal normal-case">/{{ angka($c->max_score) }}</span></th>
                        @endforeach
                        <th class="text-center">Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($participants as $rowIndex => $participant)
                        <tr wire:key="row-{{ $participant->id }}">
                            <td class="sticky start-0 z-10 max-w-40 truncate bg-white font-semibold dark:bg-hutan-900">{{ $participant->name }}</td>
                            @foreach ($components as $colIndex => $c)
                                @php($value = $scores[$c->id][$participant->id] ?? null)
                                @php($formatted = $value === null ? '' : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'))
                                <td class="p-1 text-center">
                                    <input type="text" inputmode="decimal" value="{{ $formatted }}"
                                           data-saved="{{ $formatted }}" data-c="{{ $c->id }}" data-u="{{ $participant->id }}"
                                           data-row="{{ $rowIndex }}" data-col="{{ $colIndex }}"
                                           @disabled(! $canAssess || ! $c->isEditable())
                                           aria-label="Skor {{ $c->name }} untuk {{ $participant->name }}"
                                           x-on:change.debounce.400ms="save($el)"
                                           x-on:keydown.enter.prevent="save($el); move($el, 1)"
                                           x-on:keydown.arrow-down.prevent="move($el, 1)"
                                           x-on:keydown.arrow-up.prevent="move($el, -1)"
                                           :class="{
                                               'ring-2 ring-rica': status['{{ $c->id }}-{{ $participant->id }}'] === 'error',
                                               'bg-woka-50': status['{{ $c->id }}-{{ $participant->id }}'] === 'ok',
                                           }"
                                           class="h-11 w-20 rounded-lg border border-woka-100 bg-white text-center font-semibold focus:outline-none focus:ring-2 focus:ring-woka disabled:bg-zinc-50 disabled:text-hutan/60 dark:text-santan/60 dark:border-hutan-800 dark:bg-hutan-950">
                                </td>
                            @endforeach
                            <td class="text-center font-display text-lg font-bold" x-text="finals[{{ $participant->id }}] != null ? Number(finals[{{ $participant->id }}]).toLocaleString('id-ID', {maximumFractionDigits: 1}) : '–'"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Tersimpan otomatis per sel. Enter/↓ pindah ke peserta berikutnya, ↑ ke sebelumnya, Tab ke komponen berikutnya.</p>
    @endif

    <flux:modal wire:model.self="showComponent" class="w-full max-w-md">
        <form wire:submit="saveComponent" class="space-y-4">
            <flux:heading size="lg">{{ $editingComponentId ? 'Ubah komponen' : 'Komponen baru' }}</flux:heading>
            <flux:input wire:model="component.name" label="Nama" placeholder="Speaking Practice" />
            <flux:select wire:model="component.aspect" label="Aspek">
                @foreach ($aspects as $aspect)
                    <flux:select.option :value="$aspect->value">{{ $aspect->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="component.weight" type="number" step="0.5" min="1" max="100" label="Bobot (%)" />
                <flux:input wire:model="component.max_score" type="number" step="0.5" min="1" label="Skor maksimal" />
            </div>
            @unless ($editingComponentId)
                <flux:checkbox wire:model="saveAsTemplate" label="Simpan juga sebagai template saya" />
            @endunless
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showTemplates" class="w-full max-w-md">
        <form wire:submit="applyTemplates" class="space-y-4">
            <flux:heading size="lg">Template komponen saya</flux:heading>
            @forelse ($templates as $template)
                <div class="flex items-center justify-between gap-2" wire:key="tpl-{{ $template->id }}">
                    <flux:checkbox wire:model="selectedTemplates" :value="$template->id"
                                   :label="$template->name.' · '.$template->aspect->label().' · bobot '.angka($template->weight).' · maks '.angka($template->max_score)" />
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteTemplate({{ $template->id }})" aria-label="Hapus template" />
                </div>
            @empty
                <flux:text>Belum ada template. Centang "Simpan juga sebagai template" saat membuat komponen.</flux:text>
            @endforelse
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                @if ($templates->isNotEmpty())
                    <flux:button type="submit" variant="primary">Tambahkan</flux:button>
                @endif
            </div>
        </form>
    </flux:modal>
</div>
