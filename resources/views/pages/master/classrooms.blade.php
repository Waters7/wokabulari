<?php

use App\Enums\LocationType;
use App\Enums\Role;
use App\Http\Requests\ClassroomRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Language;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kelas')] class extends Component {
    use WithCrudModal;

    #[Url]
    public ?int $batch = null;

    public function mount(): void
    {
        $this->batch ??= Batch::current()?->id;
    }

    protected function modelClass(): string
    {
        return Classroom::class;
    }

    protected function defaults(): array
    {
        return [
            'batch_id' => $this->batch ?? Batch::current()?->id,
            'language_id' => Language::query()->value('id'),
            'name' => '',
            'level' => '',
            'teacher_id' => null,
            'location_id' => Location::query()->where('type', LocationType::Classroom)->value('id'),
        ];
    }

    protected function formRules(): array
    {
        return ClassroomRequest::rulesFor();
    }

    protected function labels(): array
    {
        return ['batch_id' => 'angkatan', 'language_id' => 'bahasa', 'name' => 'nama kelas', 'teacher_id' => 'pengajar', 'location_id' => 'lokasi'];
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        return $model->participants()->exists() ? 'Kelas masih memiliki peserta.' : null;
    }

    public function with(): array
    {
        return [
            'classrooms' => Classroom::query()
                ->with(['language', 'teacher', 'location', 'batch'])
                ->withCount('participants')
                ->when($this->batch, fn ($query) => $query->where('batch_id', $this->batch))
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(20),
            'batches' => Batch::query()->orderByDesc('start_date')->get(),
            'languages' => Language::query()->orderBy('name')->get(),
            'teachers' => User::query()->role(Role::Teacher->value)->active()->orderBy('name')->get(),
            'locations' => Location::query()->where('type', LocationType::Classroom)->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Kelas" subtitle="Kelas per angkatan dan bahasa, beserta pengajar dan lokasinya.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Kelas baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row">
        <flux:select wire:model.live="batch" class="sm:max-w-xs">
            <flux:select.option value="">Semua angkatan</flux:select.option>
            @foreach ($batches as $b)
                <flux:select.option :value="$b->id">{{ $b->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari kelas…" class="sm:max-w-xs" />
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($classrooms as $classroom)
            <x-card wire:key="class-{{ $classroom->id }}">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('master.classrooms.show', $classroom) }}" wire:navigate class="min-w-0">
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $classroom->language->flag() }} {{ $classroom->language->name }} · {{ $classroom->batch->name }}</p>
                        <p class="font-display text-lg font-bold hover:text-woka">{{ $classroom->label() }}</p>
                    </a>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $classroom->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $classroom->id }})" title="Hapus kelas {{ $classroom->name }}?" />
                    </div>
                </div>
                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Pengajar</dt><dd class="truncate font-semibold">{{ $classroom->teacher?->name ?? 'Belum ditentukan' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Lokasi</dt><dd class="truncate">{{ $classroom->location?->name ?? '–' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Peserta</dt><dd class="font-semibold">{{ $classroom->participants_count }}</dd></div>
                </dl>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="building-library" title="Belum ada kelas" />
        @endforelse
    </div>
    <div class="mt-4">{{ $classrooms->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah kelas' : 'Kelas baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:select wire:model="form.batch_id" label="Angkatan" required>
                    @foreach ($batches as $b)
                        <flux:select.option :value="$b->id">{{ $b->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.language_id" label="Bahasa" required>
                    @foreach ($languages as $language)
                        <flux:select.option :value="$language->id">{{ $language->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.name" label="Nama kelas" placeholder="Kelas Inggris A" required />
                <flux:input wire:model="form.level" label="Level" placeholder="Dasar / HSK 1 / A1" />
                <flux:select wire:model="form.teacher_id" label="Pengajar utama">
                    <flux:select.option value="">– Belum ditentukan –</flux:select.option>
                    @foreach ($teachers as $teacher)
                        <flux:select.option :value="$teacher->id">{{ $teacher->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.location_id" label="Lokasi kelas">
                    <flux:select.option value="">– Pilih lokasi –</flux:select.option>
                    @foreach ($locations as $location)
                        <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
