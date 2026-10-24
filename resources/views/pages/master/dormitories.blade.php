<?php

use App\Enums\Gender;
use App\Enums\LocationType;
use App\Http\Requests\DormitoryRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Dormitory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Asrama & Kamar')] class extends Component {
    use WithCrudModal;

    protected function modelClass(): string
    {
        return Dormitory::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '',
            'gender' => Gender::Female->value,
            'location_id' => Location::query()->where('type', LocationType::Dormitory)->value('id'),
            'capacity' => 40,
            'curfew_time' => setting('dorm.curfew'),
            'check_window_start' => setting('dorm.check_window_start'),
            'check_window_end' => setting('dorm.check_window_end'),
        ];
    }

    protected function fillFrom(Model $model): array
    {
        return [
            'name' => $model->name,
            'gender' => $model->gender->value,
            'location_id' => $model->location_id,
            'capacity' => $model->capacity,
            'curfew_time' => substr($model->curfew_time, 0, 5),
            'check_window_start' => substr($model->check_window_start, 0, 5),
            'check_window_end' => substr($model->check_window_end, 0, 5),
        ];
    }

    protected function formRules(): array
    {
        return DormitoryRequest::rulesFor();
    }

    protected function labels(): array
    {
        return ['name' => 'nama asrama', 'gender' => 'jenis', 'curfew_time' => 'jam malam', 'check_window_start' => 'awal jendela absen', 'check_window_end' => 'akhir jendela absen'];
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        return $model->assignments()->whereNull('check_out_date')->exists() ? 'Asrama masih memiliki penghuni.' : null;
    }

    public function with(): array
    {
        return [
            'dormitories' => Dormitory::query()->with('location')->withCount('rooms')->orderBy('name')->get(),
            'occupancy' => \App\Models\DormRoomAssignment::query()->whereNull('check_out_date')
                ->join('dorm_rooms', 'dorm_rooms.id', '=', 'dorm_room_assignments.dorm_room_id')
                ->selectRaw('dorm_rooms.dormitory_id, count(*) as total')->groupBy('dorm_rooms.dormitory_id')->pluck('total', 'dormitory_id'),
            'locations' => Location::query()->where('type', LocationType::Dormitory)->orderBy('name')->get(),
            'genders' => Gender::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Asrama & Kamar" subtitle="Asrama putra/putri, jam malam, jendela absen malam, kamar, dan penghuni.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Asrama baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2">
        @forelse ($dormitories as $dormitory)
            <x-card wire:key="d-{{ $dormitory->id }}">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('master.dormitories.show', $dormitory) }}" wire:navigate class="min-w-0">
                        <flux:badge size="sm" :color="$dormitory->gender->color()">Asrama {{ $dormitory->gender->dormLabel() }}</flux:badge>
                        <p class="mt-1 font-display text-lg font-bold hover:text-woka">{{ $dormitory->name }}</p>
                    </a>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $dormitory->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $dormitory->id }})" title="Hapus asrama {{ $dormitory->name }}?" />
                    </div>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-hutan/60 dark:text-santan/60">Penghuni</dt><dd class="font-semibold">{{ $occupancy[$dormitory->id] ?? 0 }} / {{ $dormitory->capacity }}</dd></div>
                    <div><dt class="text-hutan/60 dark:text-santan/60">Kamar</dt><dd class="font-semibold">{{ $dormitory->rooms_count }}</dd></div>
                    <div><dt class="text-hutan/60 dark:text-santan/60">Absen malam</dt><dd class="font-semibold">{{ jam($dormitory->check_window_start) }}–{{ jam($dormitory->check_window_end) }}</dd></div>
                    <div><dt class="text-hutan/60 dark:text-santan/60">Jam malam</dt><dd class="font-semibold">{{ jam($dormitory->curfew_time) }}</dd></div>
                </dl>
                <flux:button class="mt-3 w-full" size="sm" icon="arrow-right" icon:trailing :href="route('master.dormitories.show', $dormitory)" wire:navigate>Kamar & penghuni</flux:button>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2" icon="home-modern" title="Belum ada asrama" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah asrama' : 'Asrama baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama asrama" class="sm:col-span-2" required />
                <flux:select wire:model="form.gender" label="Jenis">
                    @foreach ($genders as $gender)
                        <flux:select.option :value="$gender->value">Asrama {{ $gender->dormLabel() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.capacity" label="Kapasitas" type="number" min="0" required />
                <flux:select wire:model="form.location_id" label="Lokasi (geofence)" class="sm:col-span-2">
                    <flux:select.option value="">– Pilih lokasi –</flux:select.option>
                    @foreach ($locations as $location)
                        <flux:select.option :value="$location->id">{{ $location->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.check_window_start" label="Absen malam mulai" type="time" required />
                <flux:input wire:model="form.check_window_end" label="Absen malam selesai" type="time" required />
                <flux:input wire:model="form.curfew_time" label="Jam malam" type="time" required />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
