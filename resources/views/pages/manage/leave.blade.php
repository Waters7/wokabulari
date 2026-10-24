<?php

use App\Actions\Leave\ReviewLeaveRequest;
use App\Enums\ReviewStatus;
use App\Models\LeaveRequest;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Izin / sakit peserta')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    public ?int $reviewId = null;

    public bool $approve = true;

    public string $note = '';

    public bool $showReview = false;

    public function open(int $id, bool $approve): void
    {
        $leave = LeaveRequest::findOrFail($id);
        $this->authorize('review', $leave);
        $this->resetErrorBag();
        $this->reviewId = $id;
        $this->approve = $approve;
        $this->note = '';
        $this->showReview = true;
    }

    public function review(ReviewLeaveRequest $review): void
    {
        $leave = LeaveRequest::findOrFail($this->reviewId);
        $this->authorize('review', $leave);
        $this->resetErrorBag();

        try {
            $review->handle($leave, auth()->user(), $this->approve, $this->note ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->showReview = false;
        Flux::toast(variant: 'success', text: $this->approve ? 'Disetujui. Status kehadiran diperbarui otomatis.' : 'Pengajuan ditolak.');
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'leaves' => LeaveRequest::query()
                ->with(['user', 'reviewer'])
                ->when(! $user->can('participant.manage'), fn (Builder $q) => $q->whereHas('user.classrooms', fn ($c) => $c->where('teacher_id', $user->id)))
                ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
                ->latest()
                ->paginate(20),
            'statuses' => ReviewStatus::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Izin / sakit peserta" subtitle="Persetujuan otomatis mengisi status izin/sakit di absen harian dan sesi pada rentang tanggalnya." />

    <flux:radio.group wire:model.live="status" variant="segmented" class="mb-4 max-w-md">
        <flux:radio value="">Semua</flux:radio>
        @foreach ($statuses as $s)
            <flux:radio :value="$s->value">{{ $s->label() }}</flux:radio>
        @endforeach
    </flux:radio.group>

    <div class="space-y-2">
        @forelse ($leaves as $leave)
            <x-card wire:key="ml-{{ $leave->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $leave->user->name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                            <x-status-badge :status="$leave->type" />
                            <span>{{ $leave->periodLabel() }} ({{ $leave->dayCount() }} hari)</span>
                            <x-status-badge :status="$leave->status" />
                            @if ($leave->health_report_id)
                                <flux:badge size="sm" color="violet" icon="heart">Dari laporan sakit</flux:badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm">{{ $leave->reason }}</p>
                        @if ($leave->review_note)
                            <p class="text-xs text-hutan/60">Catatan {{ $leave->reviewer?->name }}: {{ $leave->review_note }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if ($leave->attachment_path)
                            <flux:button size="sm" icon="paper-clip" :href="$leave->attachmentUrl()" target="_blank">Lampiran</flux:button>
                        @endif
                        @if ($leave->status === ReviewStatus::Pending)
                            <flux:button size="sm" variant="primary" icon="check" wire:click="open({{ $leave->id }}, true)">Setujui</flux:button>
                            <flux:button size="sm" variant="danger" icon="x-mark" wire:click="open({{ $leave->id }}, false)">Tolak</flux:button>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="document-check" title="Tidak ada pengajuan" />
        @endforelse
    </div>
    <div class="mt-4">{{ $leaves->links() }}</div>

    <flux:modal wire:model.self="showReview" class="w-full max-w-md">
        <form wire:submit="review" class="space-y-4">
            <flux:heading size="lg">{{ $approve ? 'Setujui pengajuan' : 'Tolak pengajuan' }}</flux:heading>
            <flux:textarea wire:model="note" :label="$approve ? 'Catatan (opsional)' : 'Alasan penolakan'" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$approve ? 'primary' : 'danger'">{{ $approve ? 'Setujui' : 'Tolak' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
