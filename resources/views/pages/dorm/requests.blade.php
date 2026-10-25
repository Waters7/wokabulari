<?php

use App\Actions\Dorm\ManageDormRequests;
use App\Enums\DormRequestCategory;
use App\Http\Requests\DormRequestRequest;
use App\Models\DormRequest;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Keluhan asrama')] class extends Component {
    use WithFileUploads;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public $photo = null;

    public function create(): void
    {
        $this->authorize('create', DormRequest::class);
        $this->resetErrorBag();
        $this->form = ['category' => DormRequestCategory::BrokenFacility->value, 'description' => ''];
        $this->photo = null;
        $this->showForm = true;
    }

    public function save(ManageDormRequests $requests): void
    {
        $this->authorize('create', DormRequest::class);
        $this->resetErrorBag();

        $validator = Validator::make(array_merge($this->form, ['photo' => $this->photo]), DormRequestRequest::rulesFor(), [], ['category' => 'kategori', 'description' => 'keterangan', 'photo' => 'foto']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'photo' ? 'photo' : 'form.'.$k => $m])->all());
        }

        $requests->submit(auth()->user(), $validator->validated(), $this->photo);
        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Laporan terkirim ke pengelola asrama.');
    }

    public function with(): array
    {
        return [
            'items' => DormRequest::query()->where('user_id', auth()->id())->with('handler')->latest()->limit(30)->get(),
            'categories' => DormRequestCategory::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Keluhan & permintaan asrama" subtitle="Laporkan fasilitas rusak, kebersihan, atau kebutuhan perlengkapan.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Lapor</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-3">
        @forelse ($items as $item)
            <x-card wire:key="rq-{{ $item->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <x-status-badge :status="$item->category" />
                        <p class="mt-1 text-sm">{{ $item->description }}</p>
                        <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">{{ tanggal_jam($item->created_at) }}</p>
                        @if ($item->resolution_note)
                            <p class="mt-2 rounded-xl bg-woka-50 p-2 text-sm dark:bg-hutan-800"><span class="font-semibold">{{ $item->handler?->name ?? 'Petugas' }}:</span> {{ $item->resolution_note }}</p>
                        @endif
                    </div>
                    <x-status-badge :status="$item->status" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="wrench-screwdriver" title="Belum ada laporan" description="Ada yang rusak atau kurang di kamar? Lapor di sini, tim asrama akan menindaklanjuti." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Laporan baru</flux:heading>
            <flux:radio.group wire:model="form.category" label="Kategori" variant="cards" class="grid grid-cols-2 gap-2">
                @foreach ($categories as $category)
                    <flux:radio :value="$category->value" :label="$category->label()" />
                @endforeach
            </flux:radio.group>
            <flux:textarea wire:model="form.description" label="Keterangan" rows="3" placeholder="Keran kamar mandi bocor sejak kemarin" />
            <flux:input type="file" wire:model="photo" label="Foto (opsional)" accept="image/*" capture="environment" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
