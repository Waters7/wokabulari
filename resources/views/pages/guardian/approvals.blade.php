<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Enums\ExitPermitStatus;
use App\Livewire\Concerns\ResolvesGuardianChild;
use App\Models\DormExitPermit;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Persetujuan izin')] class extends Component {
    use ResolvesGuardianChild;

    public bool $showDecision = false;

    public ?int $decidingId = null;

    public bool $approve = true;

    public string $note = '';

    public function ask(int $id, bool $approve): void
    {
        $permit = DormExitPermit::query()->findOrFail($id);
        $this->authorize('guardianDecide', $permit);
        $this->resetErrorBag();
        $this->decidingId = $id;
        $this->approve = $approve;
        $this->note = '';
        $this->showDecision = true;
    }

    public function decide(ManageExitPermits $permits): void
    {
        $permit = DormExitPermit::query()->findOrFail($this->decidingId);
        $this->authorize('guardianDecide', $permit);

        try {
            $permits->guardianDecide($permit, auth()->user(), $this->approve, $this->note);
        } catch (\InvalidArgumentException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->showDecision = false;
        Flux::toast(variant: 'success', text: $this->approve ? 'Terima kasih, izin diteruskan ke pengelola asrama.' : 'Izin ditolak.');
    }

    public function with(): array
    {
        $childIds = $this->children()->pluck('id');

        return [
            'pending' => DormExitPermit::query()->with('user')->whereIn('user_id', $childIds)
                ->where('guardian_required', true)->where('status', ExitPermitStatus::Submitted)->orderBy('planned_departure')->get(),
            'history' => DormExitPermit::query()->with('user')->whereIn('user_id', $childIds)
                ->where(fn ($q) => $q->where('guardian_required', false)->orWhere('status', '!=', ExitPermitStatus::Submitted))
                ->latest('planned_departure')->limit(20)->get(),
            'deciding' => $this->decidingId ? DormExitPermit::query()->with('user')->find($this->decidingId) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Persetujuan izin" subtitle="Izin keluar asrama anak yang memerlukan persetujuan Bapak/Ibu." />

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Menunggu persetujuan</h2>
    <div class="mb-6 space-y-3">
        @forelse ($pending as $permit)
            <x-card wire:key="gp-{{ $permit->id }}" class="ring-2! ring-kunyit!">
                <p class="font-semibold">{{ $permit->user->name }}</p>
                <p class="text-sm">{{ $permit->type->label() }} ke <strong>{{ $permit->destination }}</strong></p>
                <p class="text-sm text-hutan/70 dark:text-santan/70">{{ $permit->periodLabel() }} WITA</p>
                <p class="mt-1 text-sm">Alasan: {{ $permit->reason }}</p>
                <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">@if ($permit->companion) Pendamping: {{ $permit->companion }} · @endif Kontak: {{ $permit->contact_phone }}</p>
                <div class="mt-3 flex gap-2">
                    <flux:button variant="primary" icon="check" class="flex-1" wire:click="ask({{ $permit->id }}, true)">Setujui</flux:button>
                    <flux:button variant="danger" icon="x-mark" class="flex-1" wire:click="ask({{ $permit->id }}, false)">Tolak</flux:button>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="check-badge" title="Tidak ada yang menunggu" description="Bapak/Ibu akan diberi kabar bila anak mengajukan izin pulang atau menginap." />
        @endforelse
    </div>

    @if ($history->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Riwayat izin keluar</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($history as $permit)
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="gh-{{ $permit->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $permit->user->name }} · {{ $permit->type->label() }}</p>
                        <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $permit->destination }} · {{ $permit->periodLabel() }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-status-badge :status="$permit->status" />
                        @if ($permit->was_late)
                            <flux:badge size="sm" color="rose">Terlambat</flux:badge>
                        @endif
                    </div>
                </div>
            @endforeach
        </x-card>
    @endif

    <flux:modal wire:model.self="showDecision" class="w-full max-w-sm">
        <form wire:submit="decide" class="space-y-4">
            <flux:heading size="lg">{{ $approve ? 'Setujui izin' : 'Tolak izin' }}</flux:heading>
            <flux:text>{{ $deciding?->user?->name }} · {{ $deciding?->type?->label() }} ke {{ $deciding?->destination }}</flux:text>
            <flux:textarea wire:model="note" :label="$approve ? 'Pesan untuk pengelola (opsional)' : 'Alasan penolakan'" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$approve ? 'primary' : 'danger'">{{ $approve ? 'Setujui' : 'Tolak' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
