<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Enums\ExitPermitStatus;
use App\Models\DormExitPermit;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Izin keluar asrama')] class extends Component {
    #[Url]
    public string $tab = 'pending';

    public bool $showDecision = false;

    public ?int $decidingId = null;

    public bool $approve = true;

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DormExitPermit::class);
    }

    public function ask(int $id, bool $approve): void
    {
        $this->authorize('decide', DormExitPermit::class);
        $this->resetErrorBag();
        $this->decidingId = $id;
        $this->approve = $approve;
        $this->note = '';
        $this->showDecision = true;
    }

    public function decide(ManageExitPermits $permits): void
    {
        $permit = DormExitPermit::query()->findOrFail($this->decidingId);
        $this->authorize('decide', $permit);

        try {
            $permits->decide($permit, auth()->user(), $this->approve, $this->note);
        } catch (\InvalidArgumentException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->showDecision = false;
        Flux::toast(variant: $this->approve ? 'success' : 'warning', text: $this->approve ? 'Izin disetujui, QR dikirim ke peserta.' : 'Izin ditolak.');
    }

    public function with(): array
    {
        $query = DormExitPermit::query()->with(['user.currentRoomAssignment.room.dormitory', 'guardian', 'approver']);

        $permits = match ($this->tab) {
            'pending' => $query->whereIn('status', [ExitPermitStatus::Submitted, ExitPermitStatus::GuardianApproved])->orderBy('planned_departure'),
            'active' => $query->whereIn('status', [ExitPermitStatus::Approved, ExitPermitStatus::Out, ExitPermitStatus::LateReturn])->orderByRaw("case status when 'late_return' then 0 when 'out' then 1 else 2 end")->orderBy('planned_return'),
            default => $query->whereIn('status', [ExitPermitStatus::Returned, ExitPermitStatus::Rejected, ExitPermitStatus::Cancelled])->latest('updated_at')->limit(50),
        };

        return [
            'permits' => $permits->get(),
            'counts' => [
                'pending' => DormExitPermit::query()->whereIn('status', [ExitPermitStatus::Submitted, ExitPermitStatus::GuardianApproved])->count(),
                'active' => DormExitPermit::query()->whereIn('status', [ExitPermitStatus::Approved, ExitPermitStatus::Out, ExitPermitStatus::LateReturn])->count(),
                'late' => DormExitPermit::query()->where('status', ExitPermitStatus::LateReturn)->count(),
            ],
            'deciding' => $this->decidingId ? DormExitPermit::query()->with('user')->find($this->decidingId) : null,
        ];
    }
}; ?>

<div wire:poll.30s>
    <x-page-header title="Izin keluar asrama" subtitle="Setujui pengajuan, pantau yang sedang keluar dan yang terlambat kembali.">
        <x-slot:actions>
            @if (Route::has('dorm.manage.scan'))
                <flux:button icon="qr-code" :href="route('dorm.manage.scan')" wire:navigate variant="primary">Pindai QR</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="mb-4">
        <flux:radio value="pending" :label="'Menunggu ('.$counts['pending'].')'" />
        <flux:radio value="active" :label="'Berlaku ('.$counts['active'].')'" />
        <flux:radio value="done" :label="'Riwayat'" />
    </flux:radio.group>

    @if ($counts['late'] > 0)
        <flux:callout icon="exclamation-triangle" color="red" class="mb-4" :text="$counts['late'].' peserta terlambat kembali. Hubungi lewat nomor kontak di bawah.'" />
    @endif

    <div class="space-y-3">
        @forelse ($permits as $permit)
            @php($room = $permit->user->currentRoomAssignment?->room)
            <x-card wire:key="mx-{{ $permit->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $permit->user->name }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $room?->dormitory?->name }}@if ($room) · Kamar {{ $room->number }}@endif</p>
                        <p class="mt-1 text-sm">{{ $permit->type->label() }} ke <strong>{{ $permit->destination }}</strong></p>
                        <p class="text-sm text-hutan/70 dark:text-santan/70">{{ $permit->periodLabel() }} WITA</p>
                        <p class="mt-1 text-sm">{{ $permit->reason }}</p>
                        <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">
                            @if ($permit->companion) Pendamping: {{ $permit->companion }} · @endif
                            Kontak: <a href="tel:{{ $permit->contact_phone }}" class="font-semibold text-woka underline">{{ $permit->contact_phone }}</a>
                        </p>
                        @if ($permit->guardian_required)
                            <p class="mt-1 text-xs">
                                @if ($permit->guardian_decided_at)
                                    Wali ({{ $permit->guardian?->name }}) menyetujui {{ tanggal_jam($permit->guardian_decided_at) }}
                                @else
                                    <span class="text-kunyit-700">Menunggu persetujuan wali</span>
                                @endif
                            </p>
                        @elseif (in_array($permit->type->value, (array) setting('exit.guardian_required_types', []), true))
                            <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Wali belum terhubung — diputuskan pengelola.</p>
                        @endif
                        @if ($permit->attachment_path)
                            <a href="{{ $permit->attachmentUrl() }}" target="_blank" class="mt-1 inline-block text-xs font-semibold text-woka underline">Lihat lampiran</a>
                        @endif
                        @if ($permit->actual_departure)
                            <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Keluar {{ tanggal_jam($permit->actual_departure) }}@if ($permit->actual_return) · kembali {{ tanggal_jam($permit->actual_return) }}@endif</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-status-badge :status="$permit->status" />
                        @if ($permit->was_late && $permit->status === \App\Enums\ExitPermitStatus::Returned)
                            <flux:badge size="sm" color="rose">Terlambat</flux:badge>
                        @endif
                    </div>
                </div>
                @if ($permit->status->isPending())
                    @php($ready = ! $permit->guardian_required || $permit->status === \App\Enums\ExitPermitStatus::GuardianApproved)
                    <div class="mt-3 flex gap-2">
                        <flux:button size="sm" variant="primary" icon="check" wire:click="ask({{ $permit->id }}, true)" :disabled="! $ready">Setujui</flux:button>
                        <flux:button size="sm" variant="danger" icon="x-mark" wire:click="ask({{ $permit->id }}, false)" :disabled="! $ready">Tolak</flux:button>
                    </div>
                @endif
            </x-card>
        @empty
            <x-empty-state icon="arrow-right-start-on-rectangle" title="Tidak ada izin" description="Belum ada izin keluar pada daftar ini." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showDecision" class="w-full max-w-sm">
        <form wire:submit="decide" class="space-y-4">
            <flux:heading size="lg">{{ $approve ? 'Setujui izin' : 'Tolak izin' }}</flux:heading>
            <flux:text>{{ $deciding?->user?->name }} · {{ $deciding?->type?->label() }}</flux:text>
            <flux:textarea wire:model="note" :label="$approve ? 'Catatan (opsional)' : 'Alasan penolakan'" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$approve ? 'primary' : 'danger'">{{ $approve ? 'Setujui' : 'Tolak' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
