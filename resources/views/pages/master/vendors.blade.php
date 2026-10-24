<?php

use App\Http\Requests\VendorRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Vendor;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor makanan')] class extends Component {
    use WithCrudModal;

    protected function modelClass(): string
    {
        return Vendor::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'contact_person' => '', 'phone' => '', 'address' => '', 'is_active' => true];
    }

    protected function formRules(): array
    {
        return VendorRequest::rulesFor();
    }

    protected function labels(): array
    {
        return ['name' => 'nama vendor', 'contact_person' => 'narahubung'];
    }

    protected function persist(array $data, ?Model $model): void
    {
        $data['phone'] = Phone::normalize($data['phone'] ?? null);
        ($model ?? new Vendor)->fill($data)->save();
    }

    public function with(): array
    {
        return [
            'vendors' => Vendor::query()
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderByDesc('is_active')->orderBy('name')->paginate(20),
        ];
    }
}; ?>

<div>
    <x-page-header title="Vendor makanan" subtitle="Penyedia konsumsi peserta (data master unit konsumsi).">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Vendor baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($vendors as $vendor)
            <x-card wire:key="v-{{ $vendor->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold">{{ $vendor->name }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $vendor->contact_person ?? '–' }} · {{ Phone::display($vendor->phone) }}</p>
                        <p class="mt-1 line-clamp-2 text-sm">{{ $vendor->address }}</p>
                    </div>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $vendor->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $vendor->id }})" title="Hapus vendor {{ $vendor->name }}?" />
                    </div>
                </div>
                @unless ($vendor->is_active)
                    <flux:badge size="sm" color="zinc" class="mt-2">Nonaktif</flux:badge>
                @endunless
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="building-storefront" title="Belum ada vendor" />
        @endforelse
    </div>
    <div class="mt-4">{{ $vendors->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah vendor' : 'Vendor baru' }}</flux:heading>
            <flux:input wire:model="form.name" label="Nama vendor" required />
            <flux:input wire:model="form.contact_person" label="Narahubung" />
            <flux:input wire:model="form.phone" label="No. HP" type="tel" />
            <flux:textarea wire:model="form.address" label="Alamat" rows="2" />
            <flux:switch wire:model="form.is_active" label="Vendor aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
