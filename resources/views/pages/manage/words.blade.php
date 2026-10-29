<?php

use App\Http\Requests\VocabularyWordRequest;
use App\Models\Language;
use App\Models\VocabularyWord;
use App\Services\DailyVocabulary;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bank kosakata')] class extends Component {
    use WithPagination;

    #[Url(as: 'bahasa')]
    public ?int $languageId = null;

    #[Url(as: 'cari')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('viewAny', VocabularyWord::class);
        $this->languageId ??= Language::query()->where('code', DailyVocabulary::FALLBACK_LANGUAGE)->value('id') ?? Language::query()->value('id');
        $this->resetForm();
    }

    public function updatedLanguageId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'language_id' => $this->languageId, 'word' => '', 'reading' => '', 'meaning' => '', 'example' => '', 'example_meaning' => '',
            'topic' => '', 'sort_order' => (int) VocabularyWord::query()->where('language_id', $this->languageId)->max('sort_order') + 1, 'is_active' => true,
        ];
    }

    public function create(): void
    {
        $this->authorize('create', VocabularyWord::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $word = VocabularyWord::query()->findOrFail($id);
        $this->authorize('update', $word);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = $word->only(['language_id', 'word', 'reading', 'meaning', 'example', 'example_meaning', 'topic', 'sort_order', 'is_active']);
        $this->form = array_map(fn ($v) => $v ?? '', $this->form);
        $this->showForm = true;
    }

    public function save(): void
    {
        $word = $this->editingId ? VocabularyWord::query()->findOrFail($this->editingId) : null;
        $word ? $this->authorize('update', $word) : $this->authorize('create', VocabularyWord::class);
        $this->resetErrorBag();

        $data = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $this->form);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $validator = Validator::make($data, VocabularyWordRequest::rulesFor(), [], VocabularyWordRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $attributes = $validator->validated() + ['sort_order' => 0];
        $word ? $word->update($attributes) : VocabularyWord::query()->create($attributes + ['created_by' => auth()->id()]);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Kata "'.$attributes['word'].'" tersimpan.');
    }

    public function delete(int $id): void
    {
        $word = VocabularyWord::query()->findOrFail($id);
        $this->authorize('delete', $word);
        $word->delete();
        Flux::toast(text: 'Kata dihapus dari bank.');
    }

    public function with(DailyVocabulary $daily): array
    {
        $language = Language::query()->find($this->languageId);

        return [
            'languages' => Language::query()->orderBy('name')->get(),
            'language' => $language,
            'cjk' => in_array($language?->code, ['zh', 'ja'], true),
            'today' => $language ? $daily->words($language, today()) : collect(),
            'total' => VocabularyWord::query()->where('language_id', $this->languageId)->active()->count(),
            'words' => VocabularyWord::query()->where('language_id', $this->languageId)->search($this->search)
                ->orderBy('sort_order')->orderBy('id')->paginate(30),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    @if ($cjk)
        <x-cjk-fonts />
    @endif

    <x-page-header title="Bank kosakata" subtitle="Kata untuk kartu kosakata harian peserta: 5 kata per hari, bergilir otomatis sesuai urutan.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Tambah kata</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="languageId" label="Bahasa">
            @foreach ($languages as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.300ms="search" label="Cari" icon="magnifying-glass" placeholder="Kata, arti, atau topik" />
    </div>

    <x-card class="mb-4">
        <p class="text-sm font-semibold">Kartu hari ini ({{ angka($total) }} kata aktif, satu putaran ± {{ angka((int) ceil($total / \App\Services\DailyVocabulary::PER_DAY)) }} hari)</p>
        @if ($today->isEmpty())
            <p class="mt-1 text-sm text-hutan/60 dark:text-santan/60">Bank {{ $language?->name }} masih kosong; peserta kelas ini sementara melihat kosakata Mandarin.</p>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($today as $word)
                    <span class="rounded-full bg-woka-50 px-3 py-1 text-sm dark:bg-hutan-800"><span @class(['font-semibold', 'font-cjk' => $cjk])>{{ $word->word }}</span> · {{ $word->meaning }}</span>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
        @forelse ($words as $word)
            <div class="flex items-center gap-3 px-4 py-2.5" wire:key="w-{{ $word->id }}">
                <span class="w-8 shrink-0 text-xs tabular-nums text-hutan/40 dark:text-santan/40">{{ $word->sort_order }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate"><span @class(['text-lg font-bold', 'font-cjk' => $cjk])>{{ $word->word }}</span> <span class="text-sm text-woka dark:text-woka-light">{{ $word->reading }}</span></p>
                    <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $word->meaning }}@if ($word->topic) · {{ $word->topic }}@endif @unless ($word->is_active) · <span class="text-red-600">nonaktif</span>@endunless</p>
                </div>
                <div class="flex shrink-0">
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $word->id }})" aria-label="Ubah" />
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $word->id }})" wire:confirm="Hapus kata ini dari bank?" aria-label="Hapus" />
                </div>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Belum ada kata.</p>
        @endforelse
    </x-card>
    <div class="mt-3">{{ $words->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah kata' : 'Kata baru' }}</flux:heading>
            <flux:select wire:model="form.language_id" label="Bahasa">
                @foreach ($languages as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.word" label="Kata" placeholder="医生" />
                <flux:input wire:model="form.reading" :label="$language?->reading_label ?? 'Cara baca'" placeholder="yīshēng" />
            </div>
            <flux:input wire:model="form.meaning" label="Arti" placeholder="Dokter" />
            <flux:input wire:model="form.example" label="Contoh kalimat (opsional)" />
            <flux:input wire:model="form.example_meaning" label="Arti contoh (opsional)" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.topic" label="Topik" placeholder="Kesehatan" />
                <flux:input wire:model="form.sort_order" type="number" label="Urutan" />
            </div>
            <flux:switch wire:model="form.is_active" label="Aktif (ikut kartu harian)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
