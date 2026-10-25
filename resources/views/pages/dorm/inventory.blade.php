<?php

use App\Enums\ItemCondition;
use App\Http\Requests\DormRequestRequest;
use App\Models\DormInventory;
use App\Models\Dormitory;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Inventaris kamar')] class extends Component {
    #[Url]
    public ?int $dormitoryId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('viewAny', DormInventory::class);
        $this->dormitoryId ??= Dormitory::query()->orderBy('name')->value('id');
    }

    public function create(?int $roomId = null): void
    {
        $this->authorize('create', DormInventory::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['dorm_room_id' => $roomId, 'item_name' => '', 'quantity' => 1, 'condition' => ItemCondition::Good->value, 'note' => ''];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = DormInventory::query()->findOrFail($id);
        $this->authorize('update', $item);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = $item->only(['dorm_room_id', 'item_name', 'quantity', 'note']) + ['condition' => $item->condition->value];
        $this->showForm = true;
    }

    public function save(): void
    {
        $item = $this->editingId ? DormInventory::query()->findOrFail($this->editingId) : new DormInventory;
        $this->authorize($item->exists ? 'update' : 'create', $item->exists ? $item : DormInventory::class);

        $validator = Validator::make($this->form, DormRequestRequest::inventoryRules(), [], ['dorm_room_id' => 'kamar', 'item_name' => 'nama barang', 'quantity' => 'jumlah', 'condition' => 'kondisi']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $item->fill($validator->validated())->save();
        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Inventaris tersimpan.');
    }

    public function delete(int $id): void
    {
        $item = DormInventory::query()->findOrFail($id);
        $this->authorize('delete', $item);
        $item->delete();
        Flux::toast(text: 'Barang dihapus.');
    }

    public function with(): array
    {
        $dormitory = Dormitory::query()->with(['rooms' => fn ($q) => $q->with(['inventories' => fn ($i) => $i->orderBy('item_name')])])->find($this->dormitoryId);

        return [
            'dormitories' => Dormitory::query()->orderBy('name')->get(),
            'dormitory' => $dormitory,
            'conditions' => ItemCondition::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Inventaris kamar" subtitle="Barang di setiap kamar beserta jumlah dan kondisinya." />

    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
        @foreach ($dormitories as $item)
            <button type="button" wire:click="$set('dormitoryId', {{ $item->id }})" wire:key="d-{{ $item->id }}"
                    @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold ring-1',
                        'bg-woka text-white ring-woka' => $dormitory?->id === $item->id,
                        'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $dormitory?->id !== $item->id])>
                {{ $item->name }}
            </button>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($dormitory?->rooms ?? [] as $room)
            <x-card :title="'Kamar '.$room->number" :subtitle="'Lantai '.$room->floor.' · kapasitas '.$room->capacity" wire:key="room-{{ $room->id }}">
                <x-slot:action>
                    <flux:button size="sm" icon="plus" wire:click="create({{ $room->id }})">Barang</flux:button>
                </x-slot:action>
                @forelse ($room->inventories as $inv)
                    <div class="flex items-center justify-between gap-2 border-t border-woka-100 py-2 first:border-0 dark:border-hutan-800" wire:key="inv-{{ $inv->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ $inv->item_name }} <span class="font-normal text-hutan/60 dark:text-santan/60">× {{ $inv->quantity }}</span></p>
                            @if ($inv->note)<p class="text-xs text-hutan/60 dark:text-santan/60">{{ $inv->note }}</p>@endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <x-status-badge :status="$inv->condition" />
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $inv->id }})" aria-label="Ubah" />
                            <x-confirm-button action="delete({{ $inv->id }})" title="Hapus barang ini?" />
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada barang tercatat.</p>
                @endforelse
            </x-card>
        @empty
            <x-empty-state icon="archive-box" title="Belum ada kamar" description="Tambahkan kamar di menu Asrama & Kamar." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-sm">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah barang' : 'Barang baru' }}</flux:heading>
            <flux:select wire:model="form.dorm_room_id" label="Kamar">
                @foreach ($dormitory?->rooms ?? [] as $room)
                    <flux:select.option :value="$room->id">Kamar {{ $room->number }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.item_name" label="Nama barang" placeholder="Kasur" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.quantity" type="number" min="0" label="Jumlah" />
                <flux:select wire:model="form.condition" label="Kondisi">
                    @foreach ($conditions as $c)
                        <flux:select.option :value="$c->value">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:input wire:model="form.note" label="Catatan (opsional)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
