<?php

use App\Actions\Evaluations\PublishEvaluation;
use App\Models\ProgramEvaluation;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Survei & Evaluasi')] class extends Component {
    use WithPagination;

    #[Url]
    public string $state = '';

    public function publish(int $id, PublishEvaluation $publish): void
    {
        $evaluation = ProgramEvaluation::query()->findOrFail($id);
        $this->authorize('update', $evaluation);

        try {
            $notified = $publish->publish($evaluation, auth()->user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: $notified ? "Survei terbit; {$notified} responden diberi tahu." : 'Survei terjadwal; responden diberi tahu saat dibuka.');
    }

    public function closeNow(int $id, PublishEvaluation $publish): void
    {
        $evaluation = ProgramEvaluation::query()->findOrFail($id);
        $this->authorize('update', $evaluation);
        $publish->close($evaluation, auth()->user());
        Flux::toast(text: 'Survei ditutup.');
    }

    public function delete(int $id): void
    {
        $evaluation = ProgramEvaluation::query()->findOrFail($id);

        // Super admin melewati policy, jadi cek jawaban tetap eksplisit.
        if (! auth()->user()->can('delete', $evaluation) || $evaluation->responses()->exists()) {
            Flux::toast(variant: 'danger', text: 'Survei yang sudah ada jawabannya tidak bisa dihapus.');

            return;
        }

        $evaluation->delete();
        Flux::toast(text: 'Survei dihapus.');
    }

    public function updatingState(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $evaluations = ProgramEvaluation::query()
            ->with('batches')
            ->withCount(['responses', 'questions'])
            ->when($this->state === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($this->state === 'open', fn ($q) => $q->open())
            ->when($this->state === 'closed', fn ($q) => $q->published()->where('closes_at', '<=', now()))
            ->latest()
            ->paginate(12);

        return [
            'evaluations' => $evaluations,
            // Jumlah responden dihitung per survei di halaman ini saja.
            'targets' => $evaluations->getCollection()->mapWithKeys(fn (ProgramEvaluation $e) => [$e->id => $e->respondents()->count()]),
            'canManage' => auth()->user()->can('evaluation.manage'),
        ];
    }
}; ?>

<div>
    <x-page-header title="Survei & Evaluasi" subtitle="Survei sarana & prasarana dan evaluasi program untuk peserta dan tim pengajar per angkatan.">
        @if ($canManage)
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" :href="route('manage.evaluations.create')" wire:navigate>Survei baru</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <flux:radio.group wire:model.live="state" variant="segmented" class="mb-4 max-w-md">
        <flux:radio value="" label="Semua" class="data-checked:bg-woka! data-checked:text-white!" />
        <flux:radio value="draft" label="Draf" class="data-checked:bg-woka! data-checked:text-white!" />
        <flux:radio value="open" label="Berjalan" class="data-checked:bg-woka! data-checked:text-white!" />
        <flux:radio value="closed" label="Selesai" class="data-checked:bg-woka! data-checked:text-white!" />
    </flux:radio.group>

    @if ($evaluations->isEmpty())
        <x-empty-state icon="clipboard-document-list" title="Belum ada survei"
                       description="Buat survei sarana & prasarana dari templat, pilih angkatan dan responden, lalu tentukan tenggatnya." />
    @else
        <div class="grid gap-3 lg:grid-cols-2">
            @foreach ($evaluations as $evaluation)
                @php([$stateLabel, $stateColor] = $evaluation->state())
                @php($target = $targets[$evaluation->id] ?? 0)
                <x-card wire:key="ev-{{ $evaluation->id }}" class="min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1">
                                <x-status-badge :status="$evaluation->category" />
                                <flux:badge size="sm" :color="$stateColor">{{ $stateLabel }}</flux:badge>
                                @if ($evaluation->is_anonymous)
                                    <flux:badge size="sm" icon="eye-slash">Anonim</flux:badge>
                                @endif
                            </div>
                            <a href="{{ route('manage.evaluations.show', $evaluation) }}" wire:navigate class="mt-1 block font-display text-lg font-bold leading-tight hover:text-woka">{{ $evaluation->title }}</a>
                        </div>
                        @if ($canManage)
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Aksi" />
                                <flux:menu>
                                    <flux:menu.item icon="chart-bar" :href="route('manage.evaluations.show', $evaluation)" wire:navigate>Hasil</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" :href="route('manage.evaluations.edit', $evaluation)" wire:navigate>Ubah</flux:menu.item>
                                    @if (! $evaluation->isPublished())
                                        <flux:menu.item icon="paper-airplane" wire:click="publish({{ $evaluation->id }})">Terbitkan</flux:menu.item>
                                    @elseif ($evaluation->isOpen())
                                        <flux:menu.item icon="lock-closed" wire:click="closeNow({{ $evaluation->id }})" wire:confirm="Tutup survei sekarang? Responden tidak bisa mengisi lagi.">Tutup sekarang</flux:menu.item>
                                    @endif
                                    @if ($evaluation->responses_count === 0)
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $evaluation->id }})" wire:confirm="Hapus survei {{ $evaluation->title }}?">Hapus</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </div>

                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Angkatan</dt><dd class="min-w-0 truncate text-end font-semibold">{{ $evaluation->batches->pluck('name')->join(', ') ?: '–' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Responden</dt><dd class="text-end">{{ collect(['Peserta' => $evaluation->target_participants, 'Pengajar' => $evaluation->target_teachers])->filter()->keys()->join(' & ') }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Tenggat</dt><dd class="text-end">{{ tanggal_jam($evaluation->closes_at) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-hutan/60 dark:text-santan/60">Pertanyaan</dt><dd>{{ $evaluation->questions_count }}</dd></div>
                    </dl>

                    @if ($evaluation->isPublished())
                        <div class="mt-3">
                            <div class="mb-1 flex justify-between text-xs text-hutan/60 dark:text-santan/60">
                                <span>Sudah mengisi</span><span class="font-semibold">{{ $evaluation->responses_count }}/{{ $target }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800">
                                <div class="h-full rounded-full bg-woka" style="width: {{ $target ? min(100, round($evaluation->responses_count / $target * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @endif
                </x-card>
            @endforeach
        </div>
        <div class="mt-4">{{ $evaluations->links() }}</div>
    @endif
</div>
