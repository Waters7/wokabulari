<?php

use App\Enums\LanguageAspect;
use App\Http\Requests\AssessmentAspectRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\AssessmentAspect;
use App\Models\Language;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Aspek penilaian')] class extends Component {
    use WithCrudModal;

    /** '' = semua, 'general' = berlaku semua bahasa, atau id bahasa */
    #[Url(as: 'bahasa')]
    public string $languageFilter = '';

    protected function modelClass(): string
    {
        return AssessmentAspect::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'language_id' => '', 'description' => '', 'source_aspect' => '', 'is_default' => false, 'is_active' => true, 'sort_order' => 100];
    }

    protected function formRules(): array
    {
        return AssessmentAspectRequest::rulesFor($this->editingId ? AssessmentAspect::find($this->editingId) : null, $this->form['language_id'] ?? null);
    }

    protected function labels(): array
    {
        return ['name' => 'nama aspek', 'language_id' => 'bahasa', 'description' => 'petunjuk', 'source_aspect' => 'sumber saran nilai', 'sort_order' => 'urutan'];
    }

    protected function fillFrom(Model $model): array
    {
        return [
            'name' => $model->name, 'language_id' => (string) ($model->language_id ?? ''), 'description' => $model->description ?? '',
            'source_aspect' => $model->source_aspect?->value ?? '', 'is_default' => $model->is_default, 'is_active' => $model->is_active, 'sort_order' => $model->sort_order,
        ];
    }

    protected function persist(array $data, ?Model $model): void
    {
        $model ??= new AssessmentAspect;
        $model->fill([
            'name' => trim($data['name']),
            'language_id' => filled($data['language_id'] ?? null) ? (int) $data['language_id'] : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'source_aspect' => filled($data['source_aspect'] ?? null) ? $data['source_aspect'] : null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) $data['sort_order'],
        ])->save();
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        return match (true) {
            $model->isBuiltIn() => 'Aspek bawaan tidak bisa dihapus. Nonaktifkan saja bila tidak dipakai.',
            $model->scores()->exists() => 'Aspek sudah berisi nilai peserta. Nonaktifkan saja agar tidak bisa dipilih lagi.',
            default => null,
        };
    }

    public function toggleActive(int $id): void
    {
        $aspect = AssessmentAspect::query()->findOrFail($id);
        $this->authorize('update', $aspect);
        $aspect->update(['is_active' => ! $aspect->is_active]);
        Flux::toast(text: $aspect->is_active ? "{$aspect->name} diaktifkan." : "{$aspect->name} dinonaktifkan; tidak muncul lagi di pilihan pengajar.");
    }

    public function with(): array
    {
        return [
            'aspects' => AssessmentAspect::query()->with('language')->withCount(['scores', 'classrooms'])
                ->when($this->languageFilter === 'general', fn ($q) => $q->whereNull('language_id'))
                ->when(ctype_digit($this->languageFilter), fn ($q) => $q->where('language_id', (int) $this->languageFilter))
                ->ordered()->get(),
            'languages' => Language::query()->orderBy('name')->get(['id', 'code', 'name']),
            'sources' => LanguageAspect::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Aspek penilaian" subtitle="Daftar aspek untuk penilaian mingguan/bulanan. Pengajar memilih aspek yang dinilai untuk tiap kelas.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Aspek baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-4">
        <ol class="grid gap-3 text-sm sm:grid-cols-3">
            <li class="flex gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-woka-50 text-xs font-bold text-woka dark:bg-hutan-800 dark:text-woka-light">1</span><span>Admin menambah aspek di sini, umum atau khusus satu bahasa (mis. <em>Hanzi</em> untuk Mandarin).</span></li>
            <li class="flex gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-woka-50 text-xs font-bold text-woka dark:bg-hutan-800 dark:text-woka-light">2</span><span>Aspek <strong>bawaan</strong> otomatis dipakai kelas yang belum memilih aspek sendiri.</span></li>
            <li class="flex gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-woka-50 text-xs font-bold text-woka dark:bg-hutan-800 dark:text-woka-light">3</span><span>Pengajar memilih aspek kelasnya di <strong>Penilaian Periodik → Atur aspek</strong>.</span></li>
        </ol>
    </x-card>

    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
        @foreach (['' => 'Semua', 'general' => 'Semua bahasa'] + $languages->mapWithKeys(fn ($l) => [(string) $l->id => $l->name])->all() as $value => $label)
            <button type="button" wire:click="$set('languageFilter', '{{ $value }}')" wire:key="lf-{{ $value ?: 'all' }}"
                    @class(['shrink-0 rounded-full border px-3 py-1.5 text-sm font-semibold transition',
                        'border-woka bg-woka text-white' => $languageFilter === (string) $value,
                        'border-woka-100 bg-white text-hutan hover:border-woka dark:border-hutan-800 dark:bg-hutan-900 dark:text-santan' => $languageFilter !== (string) $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($aspects as $aspect)
            <x-card wire:key="aspect-{{ $aspect->id }}" :class="$aspect->is_active ? '' : 'opacity-60'">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold leading-tight">{{ $aspect->name }}</p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <flux:badge size="sm" color="{{ $aspect->language ? 'sky' : 'zinc' }}">{{ $aspect->language?->name ?? 'Semua bahasa' }}</flux:badge>
                            @if ($aspect->is_default)<flux:badge size="sm" color="green">Bawaan</flux:badge>@endif
                            @unless ($aspect->is_active)<flux:badge size="sm" color="red">Nonaktif</flux:badge>@endunless
                        </div>
                    </div>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $aspect->id }})" aria-label="Ubah {{ $aspect->name }}" />
                        @unless ($aspect->isBuiltIn() || $aspect->scores_count > 0)
                            <x-confirm-button action="delete({{ $aspect->id }})" title="Hapus aspek {{ $aspect->name }}?" />
                        @endunless
                    </div>
                </div>
                @if ($aspect->description)
                    <p class="mt-2 text-sm text-hutan/70 dark:text-santan/70">{{ $aspect->description }}</p>
                @endif
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Saran nilai dari</dt><dd class="text-end font-semibold">{{ $aspect->source_aspect ? 'komponen '.$aspect->source_aspect->label() : 'diisi manual' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Dipakai</dt><dd class="text-end font-semibold">{{ $aspect->classrooms_count }} kelas memilih · {{ angka($aspect->scores_count) }} nilai</dd></div>
                </dl>
                <div class="mt-3 border-t border-woka-100 pt-2 dark:border-hutan-800">
                    <button type="button" wire:click="toggleActive({{ $aspect->id }})" class="text-sm font-semibold text-woka hover:underline dark:text-woka-light">
                        {{ $aspect->is_active ? 'Nonaktifkan' : 'Aktifkan lagi' }}
                    </button>
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="adjustments-horizontal" title="Belum ada aspek untuk filter ini" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah aspek' : 'Aspek baru' }}</flux:heading>
            <flux:input wire:model="form.name" label="Nama aspek" placeholder="Pengucapan, Hanzi, Kanji…" required />
            <flux:select wire:model="form.language_id" label="Berlaku untuk">
                <flux:select.option value="">Semua bahasa</flux:select.option>
                @foreach ($languages as $language)
                    <flux:select.option :value="(string) $language->id">Khusus {{ $language->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="form.description" label="Petunjuk penilaian (opsional)" rows="2" placeholder="Apa yang dinilai pada aspek ini" />
            <flux:select wire:model="form.source_aspect" label="Saran nilai otomatis dari" description="Rata-rata komponen sesi, quiz, dan tugas beraspek ini pada periode tersebut.">
                <flux:select.option value="">Tidak ada (diisi manual)</flux:select.option>
                @foreach ($sources as $source)
                    <flux:select.option :value="$source->value">Komponen {{ $source->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="number" wire:model="form.sort_order" label="Urutan tampil" min="0" max="9999" description="Angka kecil tampil lebih dulu." />
            <flux:switch wire:model="form.is_default" label="Aspek bawaan" description="Otomatis dipakai kelas yang belum memilih aspek sendiri." />
            <flux:switch wire:model="form.is_active" label="Aktif" description="Aspek nonaktif tidak muncul di pilihan pengajar; nilai lama tetap tersimpan." />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
