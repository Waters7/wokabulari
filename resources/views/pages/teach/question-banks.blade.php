<?php

use App\Http\Requests\QuizRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Language;
use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Bank soal')] class extends Component {
    use WithCrudModal;

    #[Url]
    public ?int $language = null;

    protected function modelClass(): string
    {
        return QuestionBank::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'language_id' => $this->language ?? Language::query()->value('id'), 'level' => '', 'topic' => ''];
    }

    protected function formRules(): array
    {
        return QuizRequest::bankRules();
    }

    protected function labels(): array
    {
        return ['name' => 'nama bank', 'language_id' => 'bahasa'];
    }

    protected function persist(array $data, ?Model $model): void
    {
        ($model ?? new QuestionBank(['teacher_id' => auth()->id()]))->fill($data)->save();
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        return $model->questions()->whereHas('quizzes')->exists() ? 'Soal di bank ini masih dipakai quiz.' : null;
    }

    public function with(): array
    {
        return [
            'banks' => QuestionBank::query()
                ->with(['language', 'teacher'])
                ->withCount('questions')
                ->when($this->language, fn ($q) => $q->where('language_id', $this->language))
                ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('topic', 'like', "%{$this->search}%")))
                ->orderBy('name')
                ->paginate(24),
            'languages' => Language::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Bank soal" subtitle="Soal per bahasa, level, dan topik — dipakai ulang lintas quiz dan angkatan.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Bank baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Nama atau topik…" />
        <flux:select wire:model.live="language">
            <flux:select.option value="">Semua bahasa</flux:select.option>
            @foreach ($languages as $l)
                <flux:select.option :value="$l->id">{{ $l->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($banks as $bank)
            <x-card wire:key="bank-{{ $bank->id }}">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('teach.questions.bank', $bank) }}" wire:navigate class="min-w-0">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $bank->language->flag() }} {{ $bank->language->name }} {{ $bank->level ? '· '.$bank->level : '' }}</p>
                        <p class="font-display text-lg font-bold hover:text-woka">{{ $bank->name }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $bank->questions_count }} soal · {{ $bank->topic ?? 'Umum' }} · {{ $bank->teacher?->name }}</p>
                    </a>
                    @can('update', $bank)
                        <div class="flex shrink-0">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $bank->id }})" aria-label="Ubah" />
                            <x-confirm-button action="delete({{ $bank->id }})" title="Hapus bank {{ $bank->name }}?" />
                        </div>
                    @endcan
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="rectangle-stack" title="Belum ada bank soal" />
        @endforelse
    </div>
    <div class="mt-4">{{ $banks->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah bank soal' : 'Bank soal baru' }}</flux:heading>
            <flux:input wire:model="form.name" label="Nama" placeholder="HSK 1 — Kosakata Rumah Sakit" />
            <flux:select wire:model="form.language_id" label="Bahasa">
                @foreach ($languages as $l)
                    <flux:select.option :value="$l->id">{{ $l->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.level" label="Level" placeholder="HSK 1, N5, A1" />
                <flux:input wire:model="form.topic" label="Topik" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
