<?php

use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Services\Reports\ReportBuilder;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Laporan mengajar')] class extends Component {
    #[Url(as: 'periode')]
    public string $period = 'monthly';

    #[Url(as: 'tanggal')]
    public string $date = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isTeacher(), 403);
        $this->date = $this->date ?: today()->toDateString();
        if (! in_array($this->period, [ReportPeriod::Weekly->value, ReportPeriod::Monthly->value], true)) {
            $this->period = ReportPeriod::Monthly->value;
        }
    }

    public function shift(int $steps): void
    {
        $this->date = ReportPeriod::from($this->period)->shift($this->date, $steps)->toDateString();
    }

    /** Laporan mengajar pengajar yang sedang login saja. */
    protected function report(ReportBuilder $builder): array
    {
        return $builder->build(ReportType::Teaching, ReportPeriod::from($this->period), CarbonImmutable::parse($this->date), null, null, auth()->id());
    }

    public function export(ReportBuilder $builder)
    {
        $report = $this->report($builder);
        $from = ReportPeriod::from($this->period)->range($this->date)[0];

        return Excel::download(new ReportExport($report, ['Pengajar: '.auth()->user()->name]), 'laporan-mengajar-'.$from->format('Y-m-d').'.xlsx');
    }

    public function with(ReportBuilder $builder): array
    {
        $report = $this->report($builder);

        return [
            'report' => $report,
            'details' => $report['sheets'][1]['rows'],
            'periods' => [ReportPeriod::Weekly, ReportPeriod::Monthly],
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Laporan mengajar" subtitle="Ngana tercatat hadir di sesi bila membuat kode kehadiran dan ada peserta yang masuk.">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" wire:click="export">Unduh Excel</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <flux:radio.group wire:model.live="period" variant="segmented" aria-label="Periode">
            @foreach ($periods as $option)
                <flux:radio :value="$option->value" :label="$option->label()" class="data-checked:bg-woka! data-checked:text-white!" />
            @endforeach
        </flux:radio.group>
        <div class="flex items-center gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Periode sebelumnya" />
            <p class="min-w-0 text-sm font-semibold">{{ $report['period'] }}</p>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Periode berikutnya" />
        </div>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4" data-test="teaching-summary">
        @foreach (array_slice($report['summary'], 1) as $item)
            <div class="rounded-2xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $item['label'] }}</p>
                <p class="font-display text-xl font-bold tabular-nums">{{ $item['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
        @forelse ($details as $i => [$date, $time, $class, $topic, $teacher, $status, $codeAt, $attended, $expected, $hours])
            <div class="flex items-start gap-3 px-4 py-3 text-sm" wire:key="tr-{{ $i }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $topic }}</p>
                    <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $date }} · {{ $time }} · {{ $class }}</p>
                    <p class="text-xs text-hutan/60 dark:text-santan/60">
                        @if ($codeAt) Kode {{ $codeAt }} · @endif {{ $attended }}/{{ $expected }} peserta hadir @if ($hours > 0) · {{ angka($hours, 1) }} jam @endif
                    </p>
                </div>
                <flux:badge size="sm" :color="match ($status) { 'Hadir' => 'green', 'Tidak tercatat' => 'red', 'Belum dimulai' => 'zinc', default => 'amber' }" class="shrink-0">{{ $status }}</flux:badge>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Belum ada sesi pada periode ini.</p>
        @endforelse
    </div>
</div>
