<?php

use App\Http\Requests\LanguageRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bahasa')] class extends Component {
    use WithCrudModal;

    protected function modelClass(): string
    {
        return Language::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'exam_format' => '', 'reading_label' => ''];
    }

    protected function formRules(): array
    {
        return LanguageRequest::rulesFor($this->editingId ? Language::find($this->editingId) : null);
    }

    protected function labels(): array
    {
        return ['code' => 'kode', 'name' => 'nama bahasa', 'exam_format' => 'format ujian', 'reading_label' => 'label bacaan'];
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        return $model->classrooms()->exists() ? 'Bahasa masih dipakai kelas.' : null;
    }

    public function with(): array
    {
        return ['languages' => Language::query()->withCount('classrooms')->orderBy('name')->paginate(20)];
    }
}; ?>

<div>
    <x-page-header title="Bahasa" subtitle="Bahasa yang diajarkan beserta format ujian acuan.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Bahasa baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($languages as $language)
            <x-card wire:key="lang-{{ $language->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-3xl leading-none">{{ $language->flag() }}</p>
                        <p class="mt-2 font-display text-lg font-bold">{{ $language->name }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">Kode <span class="font-mono">{{ $language->code }}</span> · {{ $language->classrooms_count }} kelas</p>
                    </div>
                    <div class="flex">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $language->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $language->id }})" title="Hapus bahasa {{ $language->name }}?" />
                    </div>
                </div>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Ujian acuan</dt><dd class="font-semibold">{{ $language->exam_format ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Bacaan</dt><dd class="font-semibold">{{ $language->reading_label ?: '–' }}</dd></div>
                </dl>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-4" title="Belum ada bahasa" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah bahasa' : 'Bahasa baru' }}</flux:heading>
            <div class="grid grid-cols-3 gap-3">
                <flux:input wire:model="form.code" label="Kode" placeholder="en" required />
                <flux:input wire:model="form.name" label="Nama" placeholder="Inggris" class="col-span-2" required />
            </div>
            <flux:input wire:model="form.exam_format" label="Format ujian acuan" placeholder="IELTS/TOEIC, HSK, JFT/JLPT, Goethe" />
            <flux:input wire:model="form.reading_label" label="Label bacaan" placeholder="Pinyin, Romaji" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
