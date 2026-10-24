<?php

use App\Actions\Materials\StoreMaterial;
use App\Enums\MaterialVisibility;
use App\Enums\SessionStatus;
use App\Http\Requests\SessionMaterialRequest;
use App\Models\Language;
use App\Models\LearningSession;
use App\Models\SessionMaterial;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Bank materi')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $language = null;

    #[Url]
    public string $level = '';

    public bool $showForm = false;

    public ?int $copyId = null;

    public ?int $targetSession = null;

    public bool $showCopy = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public $file = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['title' => '', 'description' => '', 'kind' => 'file', 'url' => '', 'language_id' => Language::query()->value('id'), 'level' => '', 'topic' => '', 'visibility' => MaterialVisibility::Immediate->value, 'visible_from' => '', 'is_downloadable' => true];
        $this->file = null;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'language', 'level'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('create', SessionMaterial::class);
        $this->resetErrorBag();
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(StoreMaterial $store): void
    {
        $this->authorize('create', SessionMaterial::class);
        $this->resetErrorBag();
        $data = array_merge($this->form, ['file' => $this->file]);
        $rules = SessionMaterialRequest::rulesFor($this->form['kind']) + ['language_id' => ['required', 'exists:languages,id']];
        $validator = Validator::make($data, $rules, [], ['title' => 'judul', 'file' => 'berkas', 'url' => 'tautan', 'language_id' => 'bahasa']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'file' ? 'file' : 'form.'.$k => $m])->all());
        }

        $store->handle(auth()->user(), $validator->validated(), null, $this->form['kind'] === 'file' ? $this->file : null);
        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Materi masuk bank materi.');
    }

    public function openCopy(int $id): void
    {
        $this->copyId = $id;
        $this->targetSession = $this->upcomingSessions()->first()?->id;
        $this->showCopy = true;
    }

    protected function upcomingSessions()
    {
        return LearningSession::query()
            ->forTeacher(auth()->user())
            ->with('classroom')
            ->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Ongoing])
            ->whereDate('date', '>=', today())
            ->orderBy('date')->orderBy('start_time')
            ->limit(30)
            ->get();
    }

    public function copy(StoreMaterial $store): void
    {
        $session = LearningSession::findOrFail($this->targetSession);
        $this->authorize('update', $session);
        $material = SessionMaterial::findOrFail($this->copyId);

        $store->copy($material, $session, auth()->user());
        $this->showCopy = false;
        Flux::toast(variant: 'success', text: "Disalin ke sesi {$session->topic}.");
    }

    public function with(): array
    {
        return [
            'materials' => SessionMaterial::query()
                ->with(['language', 'teacher', 'session'])
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$this->search}%")->orWhere('topic', 'like', "%{$this->search}%")))
                ->when($this->language, fn ($q) => $q->where('language_id', $this->language))
                ->when($this->level, fn ($q) => $q->where('level', $this->level))
                ->latest()
                ->paginate(20),
            'languages' => Language::query()->orderBy('name')->get(),
            'levels' => SessionMaterial::query()->whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'sessions' => $this->showCopy ? $this->upcomingSessions() : collect(),
            'visibilities' => MaterialVisibility::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Bank materi" subtitle="Kumpulan materi lintas sesi dan angkatan. Cari, unggah, lalu salin ke sesi.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Materi baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Judul atau topik…" />
        <flux:select wire:model.live="language">
            <flux:select.option value="">Semua bahasa</flux:select.option>
            @foreach ($languages as $l)
                <flux:select.option :value="$l->id">{{ $l->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="level">
            <flux:select.option value="">Semua level</flux:select.option>
            @foreach ($levels as $lv)
                <flux:select.option :value="$lv">{{ $lv }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($materials as $material)
            <x-card wire:key="bm-{{ $material->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800">
                        <flux:icon :name="$material->type->icon()" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $material->title }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $material->language?->name ?? '–' }} {{ $material->level ? '· '.$material->level : '' }} · {{ $material->topic ?? '–' }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $material->session ? 'Sesi '.tanggal($material->session->date) : 'Bank' }} · {{ $material->teacher?->name }}</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    @if ($material->isFile())
                        <flux:button size="sm" icon="eye" :href="$material->fileUrl()" target="_blank">Lihat</flux:button>
                    @elseif ($material->url)
                        <flux:button size="sm" icon="arrow-top-right-on-square" :href="$material->url" target="_blank">Buka</flux:button>
                    @endif
                    <flux:button size="sm" variant="primary" icon="document-duplicate" wire:click="openCopy({{ $material->id }})">Salin ke sesi</flux:button>
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="folder-open" title="Bank materi masih kosong" />
        @endforelse
    </div>
    <div class="mt-4">{{ $materials->links() }}</div>

    <flux:modal wire:model.self="showCopy" class="w-full max-w-md">
        <form wire:submit="copy" class="space-y-4">
            <flux:heading size="lg">Salin ke sesi</flux:heading>
            @if ($sessions->isEmpty())
                <flux:text>Belum ada sesi mendatang di kelas ngana.</flux:text>
            @else
                <flux:select wire:model="targetSession" label="Sesi tujuan">
                    @foreach ($sessions as $s)
                        <flux:select.option :value="$s->id">{{ tanggal($s->date, 'D, j M') }} · {{ $s->classroom->name }} · {{ $s->topic }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Salin</flux:button>
                </div>
            @endif
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Materi baru (bank)</flux:heading>
            <flux:radio.group wire:model.live="form.kind" variant="segmented">
                <flux:radio value="file" icon="arrow-up-tray">Unggah berkas</flux:radio>
                <flux:radio value="link" icon="link">Tautan</flux:radio>
            </flux:radio.group>
            <flux:input wire:model="form.title" label="Judul" required />
            @if ($form['kind'] === 'file')
                <div>
                    <flux:input type="file" wire:model="file" label="Berkas" />
                    <div wire:loading wire:target="file" class="mt-1 text-xs text-woka">Mengunggah…</div>
                    <flux:error name="file" />
                </div>
            @else
                <flux:input wire:model="form.url" label="Tautan" type="url" />
            @endif
            <div class="grid gap-3 sm:grid-cols-3">
                <flux:select wire:model="form.language_id" label="Bahasa">
                    @foreach ($languages as $l)
                        <flux:select.option :value="$l->id">{{ $l->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.level" label="Level" placeholder="HSK 1, N5, A1" />
                <flux:input wire:model="form.topic" label="Topik" />
            </div>
            <flux:textarea wire:model="form.description" label="Keterangan" rows="2" />
            <flux:switch wire:model="form.is_downloadable" label="Peserta boleh mengunduh" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
