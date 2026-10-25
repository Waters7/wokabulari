<?php

use App\Actions\Dorm\ManageDormRequests;
use App\Enums\DormRequestStatus;
use App\Enums\Priority;
use App\Models\DormRequest;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Keluhan & permintaan asrama')] class extends Component {
    #[Url]
    public string $status = 'open';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $newStatus = 'in_progress';

    public string $priority = 'normal';

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DormRequest::class);
    }

    public function edit(int $id): void
    {
        $request = DormRequest::query()->findOrFail($id);
        $this->authorize('update', $request);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->newStatus = $request->status === DormRequestStatus::New ? DormRequestStatus::InProgress->value : $request->status->value;
        $this->priority = $request->priority->value;
        $this->note = (string) $request->resolution_note;
        $this->showForm = true;
    }

    public function save(ManageDormRequests $requests): void
    {
        $request = DormRequest::query()->findOrFail($this->editingId);
        $this->authorize('update', $request);

        try {
            $requests->update($request, auth()->user(), DormRequestStatus::from($this->newStatus), $this->note, Priority::tryFrom($this->priority));
        } catch (\InvalidArgumentException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Laporan diperbarui.');
    }

    public function with(): array
    {
        return [
            'items' => DormRequest::query()->with(['user', 'room.dormitory', 'handler'])
                ->when($this->status === 'open', fn ($q) => $q->where('status', '!=', DormRequestStatus::Resolved))
                ->when($this->status === 'resolved', fn ($q) => $q->where('status', DormRequestStatus::Resolved))
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->latest()->limit(60)->get(),
            'statuses' => DormRequestStatus::cases(),
            'priorities' => Priority::cases(),
            'editing' => $this->editingId ? DormRequest::query()->with('user')->find($this->editingId) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Keluhan & permintaan" subtitle="Laporan fasilitas, kebersihan, dan perlengkapan dari peserta." />

    <flux:radio.group wire:model.live="status" variant="segmented" class="mb-4">
        <flux:radio value="open" :label="'Belum selesai'" />
        <flux:radio value="resolved" :label="'Selesai'" />
        <flux:radio value="all" :label="'Semua'" />
    </flux:radio.group>

    <div class="space-y-3">
        @forelse ($items as $item)
            <x-card wire:key="mr-{{ $item->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-1">
                            <x-status-badge :status="$item->category" />
                            <x-status-badge :status="$item->priority" />
                        </div>
                        <p class="mt-1 text-sm">{{ $item->description }}</p>
                        <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">{{ $item->user->name }} · {{ $item->room?->dormitory?->name }}@if ($item->room) kamar {{ $item->room->number }}@endif · {{ tanggal_jam($item->created_at) }}</p>
                        @if ($item->photo_path)
                            <a href="{{ $item->photoUrl() }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-woka underline">Lihat foto</a>
                        @endif
                        @if ($item->resolution_note)
                            <p class="mt-2 rounded-xl bg-woka-50 p-2 text-sm dark:bg-hutan-800">{{ $item->resolution_note }} <span class="text-xs text-hutan/60 dark:text-santan/60">— {{ $item->handler?->name }}</span></p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <x-status-badge :status="$item->status" />
                        <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $item->id }})">Proses</flux:button>
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="wrench-screwdriver" title="Tidak ada laporan" description="Semua beres untuk saat ini." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-sm">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Proses laporan</flux:heading>
            <flux:text>{{ $editing?->description }}</flux:text>
            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="newStatus" label="Status">
                    @foreach ($statuses as $s)
                        <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="priority" label="Prioritas">
                    @foreach ($priorities as $p)
                        <flux:select.option :value="$p->value">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:textarea wire:model="note" label="Catatan penanganan" rows="3" placeholder="Keran sudah diganti tukang" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
