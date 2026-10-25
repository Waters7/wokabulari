<?php

use App\Models\VendorRating;
use App\Services\MealRoster;
use App\Services\RatingSummary;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Rekap rating vendor')] class extends Component {
    /** Rentang: 0 = minggu ini, 4 = 4 minggu terakhir, 12 = 12 minggu terakhir. */
    #[Url]
    public int $weeks = 0;

    public function with(RatingSummary $summary, MealRoster $roster): array
    {
        $to = now()->startOfWeek();
        $from = $to->subWeeks(max($this->weeks - 1, 0));

        return [
            'rows' => $summary->vendors($from, $to),
            'aspects' => VendorRating::ASPECTS,
            'participants' => $this->weeks === 0 ? $roster->participants()->count() : null,
            'trend' => $summary->weeklyTrend(VendorRating::query()),
            'showNames' => auth()->user()->can('rating.view-names'),
            'from' => $from,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Rekap rating vendor" subtitle="Rata-rata rating mingguan dari peserta untuk tiap vendor konsumsi.">
        <x-slot:actions>
            <flux:select wire:model.live="weeks" size="sm" class="max-w-48" aria-label="Rentang">
                <flux:select.option value="0">Minggu ini</flux:select.option>
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
            <x-rating-summary :summary="$row" :aspects="$aspects" :title="$row['vendor']->name" :participants="$participants" :show-names="$showNames" wire:key="v-{{ $row['vendor']->id }}" />
        @empty
            <x-empty-state icon="star" title="Belum ada rating" description="Rating vendor muncul setelah peserta mengisi rating mingguan." />
        @endforelse
    </div>
</div>
