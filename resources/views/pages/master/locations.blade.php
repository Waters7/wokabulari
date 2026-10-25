<?php

use App\Enums\LocationType;
use App\Http\Requests\LocationRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Lokasi')] class extends Component {
    use WithCrudModal;

    protected function modelClass(): string
    {
        return Location::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '',
            'type' => LocationType::Classroom->value,
            // Titik contoh: kampus Poltekkes Kemenkes Manado (dapat diubah)
            'latitude' => 1.4605,
            'longitude' => 124.8228,
            'radius_m' => (int) setting('attendance.default_radius_m'),
        ];
    }

    protected function formRules(): array
    {
        return LocationRequest::rulesFor();
    }

    protected function fillFrom(Model $model): array
    {
        return [
            'name' => $model->name,
            'type' => $model->type->value,
            'latitude' => $model->latitude,
            'longitude' => $model->longitude,
            'radius_m' => $model->radius_m,
        ];
    }

    protected function labels(): array
    {
        return ['name' => 'nama lokasi', 'type' => 'jenis', 'radius_m' => 'radius'];
    }

    protected function deleteBlockedReason(Model $model): ?string
    {
        $used = \App\Models\Classroom::where('location_id', $model->id)->exists()
            || \App\Models\Dormitory::where('location_id', $model->id)->exists();

        return $used ? 'Lokasi masih dipakai kelas atau asrama.' : null;
    }

    public function with(): array
    {
        return [
            'locations' => Location::query()->orderBy('type')->orderBy('name')->get(),
            'types' => LocationType::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Lokasi" subtitle="Titik kelas dan asrama beserta radius geofence untuk absen.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Lokasi baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($locations->isNotEmpty())
        <x-card :padding="false" class="mb-4 overflow-hidden">
            <div x-data="mapViewer(@js($locations->map(fn ($l) => ['lat' => $l->latitude, 'lng' => $l->longitude, 'radius' => $l->radius_m, 'label' => $l->name, 'color' => $l->type === LocationType::Classroom ? '#007D71' : '#2563eb'])->values()))"
                 wire:ignore>
                <div x-ref="map" class="h-64 w-full sm:h-80"></div>
            </div>
        </x-card>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($locations as $location)
            <x-card wire:key="loc-{{ $location->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <x-status-badge :status="$location->type" />
                        <p class="mt-1 font-display text-lg font-bold">{{ $location->name }}</p>
                        <p class="font-mono text-xs text-hutan/60 dark:text-santan/60">{{ $location->latitude }}, {{ $location->longitude }}</p>
                        <p class="text-sm">Radius {{ angka($location->radius_m) }} m</p>
                    </div>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $location->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $location->id }})" title="Hapus lokasi {{ $location->name }}?" />
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="map-pin" title="Belum ada lokasi" description="Tambahkan lokasi kampus dan asrama untuk geofence absen." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah lokasi' : 'Lokasi baru' }}</flux:heading>

            <div class="grid gap-3 sm:grid-cols-3">
                <flux:input wire:model="form.name" label="Nama lokasi" class="sm:col-span-2" required />
                <flux:select wire:model="form.type" label="Jenis">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($showForm)
                <div wire:key="picker-{{ $editingId ?? 'new' }}" wire:ignore
                     x-data="mapPicker({ lat: {{ (float) $form['latitude'] }}, lng: {{ (float) $form['longitude'] }}, radius: {{ (int) $form['radius_m'] }}, latModel: 'form.latitude', lngModel: 'form.longitude' })"
                     x-on:radius-changed.window="setRadius($event.detail)">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <flux:text>Ketuk peta untuk memindahkan titik.</flux:text>
                        <flux:button size="sm" variant="subtle" icon="map-pin" x-on:click="useMyLocation()">Lokasi saya</flux:button>
                    </div>
                    <div x-ref="map" class="h-64 w-full overflow-hidden rounded-xl ring-1 ring-woka-100"></div>
                </div>
            @endif

            <div class="grid gap-3 sm:grid-cols-3">
                <flux:input wire:model="form.latitude" label="Lintang" type="number" step="0.0000001" required />
                <flux:input wire:model="form.longitude" label="Bujur" type="number" step="0.0000001" required />
                <flux:input wire:model="form.radius_m" label="Radius (meter)" type="number" min="10" max="5000" required
                            x-on:input="$dispatch('radius-changed', $event.target.value)" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
