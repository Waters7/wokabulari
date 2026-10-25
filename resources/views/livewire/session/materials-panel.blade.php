<?php

use App\Actions\Materials\StoreMaterial;
use App\Enums\MaterialVisibility;
use App\Http\Requests\SessionMaterialRequest;
use App\Models\LearningSession;
use App\Models\SessionMaterial;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public LearningSession $session;

    public bool $showForm = false;

    public bool $showBank = false;

    public ?int $logMaterialId = null;

    public bool $showLog = false;

    public string $bankSearch = '';

    /** @var array<string, mixed> */
    public array $form = [];

    public $file = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['title' => '', 'description' => '', 'kind' => 'file', 'url' => '', 'visibility' => MaterialVisibility::Immediate->value, 'visible_from' => '', 'is_downloadable' => true];
        $this->file = null;
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
        $this->authorize('update', $this->session);
        $this->resetErrorBag();

        $data = array_merge($this->form, ['file' => $this->file]);
        $validator = Validator::make($data, SessionMaterialRequest::rulesFor($this->form['kind']), [], ['title' => 'judul', 'file' => 'berkas', 'url' => 'tautan', 'visible_from' => 'waktu tampil']);

        if ($validator->fails()) {
            $errors = collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'file' ? 'file' : 'form.'.$k => $m]);
            throw ValidationException::withMessages($errors->all());
        }

        $store->handle(auth()->user(), $validator->validated(), $this->session, $this->form['kind'] === 'file' ? $this->file : null);

        $this->showForm = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: 'Materi tersimpan.');
    }

    public function delete(int $id): void
    {
        $material = $this->session->materials()->findOrFail($id);
        $this->authorize('delete', $material);

        if ($material->file_path) {
            Storage::disk('private')->delete($material->file_path);
        }
        $material->delete();
        Flux::toast(text: 'Materi dihapus.');
    }

    public function copyFromBank(int $id, StoreMaterial $store): void
    {
        $this->authorize('update', $this->session);
        $source = SessionMaterial::query()->findOrFail($id);
        $this->authorize('view', $source);

        $store->copy($source, $this->session, auth()->user());
        Flux::toast(variant: 'success', text: 'Materi disalin ke sesi ini.');
    }

    public function showLogs(int $id): void
    {
        $this->logMaterialId = $id;
        $this->showLog = true;
    }

    public function with(): array
    {
        $participantCount = $this->session->classroom->participants()->count();

        return [
            'materials' => $this->session->materials()->withCount(['accessLogs as viewed_count', 'accessLogs as downloaded_count' => fn ($q) => $q->whereNotNull('downloaded_at')])->get(),
            'participantCount' => $participantCount,
            'bank' => $this->showBank ? SessionMaterial::query()
                ->with('session')
                ->where(fn ($q) => $q->whereNull('learning_session_id')->orWhere('learning_session_id', '!=', $this->session->id))
                ->where(fn ($q) => $q->whereNull('language_id')->orWhere('language_id', $this->session->classroom->language_id))
                ->when($this->bankSearch, fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$this->bankSearch}%")->orWhere('topic', 'like', "%{$this->bankSearch}%")))
                ->latest()->limit(20)->get() : collect(),
            'logs' => $this->logMaterialId ? SessionMaterial::find($this->logMaterialId)?->accessLogs()->with('user')->latest('viewed_at')->get() ?? collect() : collect(),
            'canManage' => auth()->user()->can('update', $this->session),
            'visibilities' => MaterialVisibility::cases(),
        ];
    }
}; ?>

<div>
    @if ($canManage)
        <div class="mb-4 flex flex-wrap gap-2">
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah materi</flux:button>
            <flux:button icon="folder-open" wire:click="$toggle('showBank')">Ambil dari bank materi</flux:button>
        </div>
    @endif

    @if ($showBank)
        <x-card class="mb-4" title="Bank materi">
            <flux:input wire:model.live.debounce.300ms="bankSearch" icon="magnifying-glass" placeholder="Cari judul atau topik…" class="mb-3" />
            <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
                @forelse ($bank as $item)
                    <li class="flex items-center justify-between gap-3 py-2" wire:key="bank-{{ $item->id }}">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $item->title }}</p>
                            <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $item->type->label() }} · {{ $item->topic ?? $item->session?->topic ?? 'Bank' }} {{ $item->level ? '· '.$item->level : '' }}</p>
                        </div>
                        <flux:button size="sm" icon="document-duplicate" wire:click="copyFromBank({{ $item->id }})">Salin</flux:button>
                    </li>
                @empty
                    <li class="py-2 text-sm text-hutan/60 dark:text-santan/60">Tidak ada materi yang cocok.</li>
                @endforelse
            </ul>
        </x-card>
    @endif

    <div class="space-y-2">
        @forelse ($materials as $material)
            <x-card wire:key="m-{{ $material->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
                        <flux:icon :name="$material->type->icon()" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $material->title }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">
                            {{ $material->type->label() }} {{ $material->sizeLabel() ? '· '.$material->sizeLabel() : '' }}
                            · <x-status-badge :status="$material->visibility" />
                            @if ($material->visible_from) {{ tanggal_jam($material->visible_from) }} @endif
                            @unless ($material->is_downloadable) · tidak bisa diunduh @endunless
                        </p>
                        @if ($material->description)
                            <p class="mt-1 text-sm">{{ $material->description }}</p>
                        @endif
                        <button type="button" class="mt-1 text-xs font-semibold text-woka hover:underline" wire:click="showLogs({{ $material->id }})">
                            Dibuka {{ $material->viewed_count }}/{{ $participantCount }} peserta · diunduh {{ $material->downloaded_count }}
                        </button>
                    </div>
                    <div class="flex shrink-0">
                        @if ($material->isFile())
                            <flux:button size="sm" variant="ghost" icon="eye" :href="$material->fileUrl()" target="_blank" aria-label="Lihat" />
                        @elseif ($material->url)
                            <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$material->url" target="_blank" aria-label="Buka tautan" />
                        @endif
                        @if ($canManage)
                            <x-confirm-button action="delete({{ $material->id }})" title="Hapus materi {{ $material->title }}?" />
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="book-open" title="Belum ada materi" description="Unggah PDF, slide, audio, video, atau tautan YouTube/Google Drive." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Tambah materi</flux:heading>
            <flux:radio.group wire:model.live="form.kind" variant="segmented">
                <flux:radio value="file" icon="arrow-up-tray">Unggah berkas</flux:radio>
                <flux:radio value="link" icon="link">Tautan</flux:radio>
            </flux:radio.group>
            <flux:input wire:model="form.title" label="Judul" required />
            @if ($form['kind'] === 'file')
                <div>
                    <flux:input type="file" wire:model="file" label="Berkas" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,image/*,audio/*,video/mp4,video/webm"
                                :description="'PDF, PPT, DOC, gambar, audio, atau video. Maks. '.setting('files.max_upload_mb').' MB. Gambar dikompres otomatis.'" />
                    <div wire:loading wire:target="file" class="mt-1 text-xs text-woka">Mengunggah…</div>
                    <flux:error name="file" />
                </div>
            @else
                <flux:input wire:model="form.url" label="Tautan" type="url" placeholder="https://youtu.be/… atau https://drive.google.com/…" />
            @endif
            <flux:textarea wire:model="form.description" label="Keterangan" rows="2" />
            <flux:select wire:model.live="form.visibility" label="Tampil ke peserta">
                @foreach ($visibilities as $visibility)
                    <flux:select.option :value="$visibility->value">{{ $visibility->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($form['visibility'] === 'scheduled')
                <flux:input wire:model="form.visible_from" type="datetime-local" label="Tampil mulai" />
            @endif
            <flux:switch wire:model="form.is_downloadable" label="Peserta boleh mengunduh" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,save">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showLog" class="w-full max-w-md">
        <flux:heading size="lg">Akses materi</flux:heading>
        <ul class="mt-3 max-h-96 divide-y divide-woka-100 overflow-y-auto dark:divide-hutan-800">
            @forelse ($logs as $log)
                <li class="flex justify-between gap-2 py-2 text-sm">
                    <span class="font-semibold">{{ $log->user->name }}</span>
                    <span class="text-end text-xs text-hutan/60 dark:text-santan/60">Dibuka {{ tanggal_jam($log->viewed_at) }}@if ($log->downloaded_at)<br>Diunduh {{ tanggal_jam($log->downloaded_at) }}@endif</span>
                </li>
            @empty
                <li class="py-2 text-sm text-hutan/60 dark:text-santan/60">Belum ada peserta yang membuka.</li>
            @endforelse
        </ul>
    </flux:modal>
</div>
