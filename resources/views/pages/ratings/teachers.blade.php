<?php

use App\Models\TeacherRating;
use App\Services\RatingSummary;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Rekap rating pengajar')] class extends Component {
    #[Url]
    public int $weeks = 4;

    public function with(RatingSummary $summary): array
    {
        $to = now()->startOfWeek();

        return [
            'rows' => $summary->teachers($to->subWeeks(max($this->weeks - 1, 0)), $to),
            'aspects' => TeacherRating::ASPECTS,
            'trend' => $summary->weeklyTrend(TeacherRating::query()),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Rekap rating pengajar" subtitle="Rating mingguan peserta untuk pengajar. Nama penilai anonim hanya terlihat oleh admin & pimpinan.">
        <x-slot:actions>
            <flux:select wire:model.live="weeks" size="sm" class="max-w-48" aria-label="Rentang">
                <flux:select.option value="1">Minggu ini</flux:select.option>
                <flux:select.option value="4">4 minggu terakhir</flux:select.option>
                <flux:select.option value="12">12 minggu terakhir</flux:select.option>
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Tren rata-rata mingguan" class="mb-4">
        <div class="h-48" wire:ignore x-data="chart({ type: 'line', data: { labels: @js($trend['labels']), datasets: [{ label: 'Rata-rata', data: @js($trend['values']), spanGaps: true }] }, max: 5 })">
            <canvas x-ref="canvas"></canvas>
        </div>
    </x-card>

    <div class="space-y-4">
        @forelse ($rows as $row)
            <x-rating-summary :summary="$row" :aspects="$aspects" :title="$row['teacher']->name" :subtitle="$row['ratings']->first()->classroom?->name" :anonymous-aware="true" wire:key="t-{{ $row['teacher']->id }}" />
        @empty
            <x-empty-state icon="star" title="Belum ada rating" description="Rating pengajar muncul setelah peserta mengisi rating mingguan." />
        @endforelse
    </div>
</div>
