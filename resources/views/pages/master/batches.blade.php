<?php

use App\Http\Requests\BatchRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Angkatan')] class extends Component {
    use WithCrudModal;

    protected function modelClass(): string
    {
        return Batch::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeeks(12)->toDateString(),
            'attendance_target' => 80,
            'passing_score' => 70,
            'is_active' => true,
        ];
    }

    protected function formRules(): array
    {
        return BatchRequest::rulesFor();
    }

    protected function labels(): array
    {
        return ['name' => 'nama angkatan', 'attendance_target' => 'target kehadiran', 'passing_score' => 'nilai minimal lulus'];
    }

    protected function beforeDelete(Model $model): void
    {
        abort_if($model->classrooms()->exists(), 422, 'Angkatan masih memiliki kelas.');
    }

    public function with(): array
    {
        return [
            'batches' => Batch::query()
                ->withCount('classrooms')
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderByDesc('start_date')
                ->paginate(15),
        ];
    }
}; ?>

<div>
    <x-page-header title="Angkatan" subtitle="Periode program, target kehadiran, dan nilai minimal lulus.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Angkatan baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Cari angkatan…" class="mb-4 max-w-sm" />

    @if ($batches->isEmpty())
        <x-empty-state title="Belum ada angkatan" description="Buat angkatan pertama untuk mulai menyusun kelas." />
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama</th><th>Periode</th><th>Target hadir</th><th>Nilai lulus</th><th>Kelas</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        <tr wire:key="batch-{{ $batch->id }}">
                            <td class="font-semibold">{{ $batch->name }}</td>
                            <td>{{ tanggal($batch->start_date) }} – {{ tanggal($batch->end_date) }}</td>
                            <td>{{ $batch->attendance_target }}%</td>
                            <td>{{ angka($batch->passing_score) }}</td>
                            <td>{{ $batch->classrooms_count }}</td>
                            <td>
                                <flux:badge :color="$batch->is_active ? 'green' : 'zinc'" size="sm">{{ $batch->is_active ? 'Aktif' : 'Selesai' }}</flux:badge>
                            </td>
                            <td class="text-end">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $batch->id }})" aria-label="Ubah" />
                                <x-confirm-button action="delete({{ $batch->id }})" title="Hapus angkatan {{ $batch->name }}?" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $batches->links() }}</div>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah angkatan' : 'Angkatan baru' }}</flux:heading>

            <flux:input wire:model="form.name" label="Nama angkatan" placeholder="Angkatan 1 2026" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.start_date" type="date" label="Tanggal mulai" required />
                <flux:input wire:model="form.end_date" type="date" label="Tanggal selesai" required />
                <flux:input wire:model="form.attendance_target" type="number" min="1" max="100" label="Target kehadiran (%)" required />
                <flux:input wire:model="form.passing_score" type="number" step="0.01" min="0" max="100" label="Nilai minimal lulus" required />
            </div>
            <flux:switch wire:model="form.is_active" label="Angkatan aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
