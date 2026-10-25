<?php

use App\Models\TeacherRating;
use App\Services\RatingSummary;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Rating saya')] class extends Component {
    #[Url]
    public int $weeks = 4;

    public function with(RatingSummary $summary): array
    {
        $to = now()->startOfWeek();
        $teacher = auth()->user();

        return [
            'row' => $summary->teachers($to->subWeeks(max($this->weeks - 1, 0)), $to, $teacher->id)->first(),
            'aspects' => TeacherRating::ASPECTS,
            'trend' => $summary->weeklyTrend(TeacherRating::query()->where('teacher_id', $teacher->id)),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Rating saya" subtitle="Masukan mingguan dari peserta kelas. Penilai anonim tidak ditampilkan namanya.">
        <x-slot:actions>
            <flux:select wire:model.live="weeks" size="sm" class="max-w-48" aria-label="Rentang">
                <flux:select.option value="1">Minggu ini</flux:select.option>
                <flux:select.option value="4">4 minggu terakhir</flux:select.option>
                <flux:select.option value="12">12 minggu terakhir</flux:select.option>
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    @if ($row)
        <x-rating-summary :summary="$row" :aspects="$aspects" title="Rata-rata ngana" :anonymous-aware="true" class="mb-4" />
    @else
        <x-empty-state icon="star" title="Belum ada rating" description="Peserta mengisi rating pengajar setiap minggu; pengingat dikirim tiap Jumat." class="mb-4" />
    @endif

    <x-card title="Tren rata-rata mingguan">
        <div class="h-48" wire:ignore x-data="chart({ type: 'line', data: { labels: @js($trend['labels']), datasets: [{ label: 'Rata-rata', data: @js($trend['values']), spanGaps: true }] }, max: 5 })">
            <canvas x-ref="canvas"></canvas>
        </div>
    </x-card>
</div>
