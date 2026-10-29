<?php

use App\Models\Batch;
use App\Models\Classroom;
use App\Services\Reports\AttendanceAnalytics;
use App\Services\Reports\ExecutiveDashboard;
use App\Services\Reports\ReportFilter;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Dashboard eksekutif')] class extends Component {
    #[Url(as: 'angkatan')]
    public ?int $batchId = null;

    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    #[Url(as: 'peserta')]
    public ?int $participantId = null;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.leader'), 403);
        $this->batchId ??= Batch::current()?->id;
        if ($this->from === '' || $this->to === '') {
            $this->preset(14);
        }
    }

    public function updatedBatchId(): void
    {
        $this->classroomId = null;
        $this->participantId = null;
    }

    /** Rentang cepat: N hari terakhir, atau 0 = sejak awal angkatan. */
    public function preset(int $days): void
    {
        $batch = Batch::query()->find($this->batchId);
        $end = today()->toImmutable();
        if ($batch && $batch->end_date->lt($end)) {
            $end = $batch->end_date->toImmutable()->startOfDay();
        }

        $start = $days > 0 ? $end->subDays($days - 1) : ($batch?->start_date->toImmutable() ?? $end->subDays(29));
        if ($batch && $start->lt($batch->start_date)) {
            $start = $batch->start_date->toImmutable()->startOfDay();
        }

        $this->from = $start->toDateString();
        $this->to = $end->toDateString();
    }

    public function showParticipant(?int $id): void
    {
        $this->participantId = $id;
    }

    protected function filter(): ReportFilter
    {
        return ReportFilter::make(CarbonImmutable::parse($this->from ?: today()), CarbonImmutable::parse($this->to ?: today()), $this->batchId, $this->classroomId);
    }

    public function with(ExecutiveDashboard $dashboard): array
    {
        $filter = $this->filter();
        $data = $dashboard->overview($filter);
        $people = collect($data['people'])
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['name']), mb_strtolower($this->search))))
            // Yang perlu perhatian di atas, lalu kehadiran terendah.
            ->sortBy(fn ($p) => [-count($p['flags']), $p['attendance'] ?? 101, $p['name']])->values();

        return [
            'filter' => $filter,
            'data' => $data,
            'people' => $people,
            'attention' => collect($data['people'])->filter(fn ($p) => $p['flags'] !== [])->sortBy(fn ($p) => [-count($p['flags']), $p['attendance'] ?? 101])->values(),
            'detail' => $this->participantId ? $dashboard->participant($filter, $this->participantId) : null,
            'batches' => Batch::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classrooms' => Classroom::query()->when($this->batchId, fn ($q) => $q->where('batch_id', $this->batchId))->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>


<div>
    @php
        $pct = fn ($v) => $v === null ? '–' : angka($v, 1).'%';
        $num = fn ($v) => $v === null ? '–' : angka($v, 1);
        $statusColors = ['present' => '#0ca30c', 'late' => '#fab219', 'excused' => '#2a78d6', 'sick' => '#6250d6', 'absent' => '#d03b3b', 'rejected' => '#ec835a', 'missing' => '#898781'];
        $charts = $data['charts'];
        $section = 'mb-2 mt-8 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45';
        $panel = 'rounded-2xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800';
    @endphp

    <x-page-header title="Dashboard eksekutif" :subtitle="tanggal($filter->from, 'j M Y').' – '.tanggal($filter->to, 'j M Y').' · data diperbarui tiap 5 menit'">
        <x-slot:actions>
            <flux:button icon="document-arrow-down" :href="route('reports.index')" wire:navigate>Pusat laporan</flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter dalam satu baris di atas semua grafik --}}
    <div class="{{ $panel }} mb-5 space-y-3">
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="grid grid-cols-2 gap-3 [&>*]:min-w-0">
                <flux:select wire:model.live="batchId" label="Angkatan" placeholder="Semua angkatan">
                    @foreach ($batches as $batch)
                        <flux:select.option :value="$batch->id">{{ $batch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="classroomId" label="Kelas">
                    <flux:select.option value="">Semua kelas</flux:select.option>
                    @foreach ($classrooms as $classroom)
                        <flux:select.option :value="$classroom->id">{{ $classroom->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            {{-- Input tanggal bawaan browser butuh ±150px: di HP ditumpuk. --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model.live="from" type="date" label="Dari" />
                <flux:input wire:model.live="to" type="date" label="Sampai" />
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach ([7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 0 => 'Sejak awal angkatan'] as $days => $label)
                <flux:button size="xs" variant="ghost" wire:click="preset({{ $days }})">{{ $label }}</flux:button>
            @endforeach
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="transition">
        {{-- Angka utama --}}
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-3 xl:grid-cols-6" data-test="exec-kpis">
            @foreach ([
                ['Peserta aktif', angka($data['kpis']['participants']), null],
                ['Kehadiran harian', $pct($data['kpis']['attendance']), 'target '.angka($data['target']).'%'],
                ['Kehadiran sesi', $pct($data['kpis']['sessions']), $data['kpis']['sessionCount'].' sesi'],
                ['Rata-rata nilai', $num($data['kpis']['score']), 'batas lulus '.angka($data['passing'])],
                ['Serapan konsumsi', $pct($data['kpis']['meals']), angka($data['kpis']['received']).' tanda terima'],
                ['Porsi tak diambil', angka($data['kpis']['leftover']), 'diterima vendor − tanda terima'],
            ] as [$label, $value, $hint])
                <div class="rounded-2xl bg-white px-4 py-3 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                    <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $label }}</p>
                    <p class="font-display text-2xl font-extrabold tabular-nums">{{ $value }}</p>
                    @if ($hint)<p class="truncate text-xs text-hutan/50 dark:text-santan/50">{{ $hint }}</p>@endif
                </div>
            @endforeach
        </div>

        @if ($data['insights'] !== [])
            <h2 class="{{ $section }}">Catatan untuk keputusan</h2>
            <ul class="{{ $panel }} space-y-2 text-sm" data-test="exec-insights">
                @foreach ($data['insights'] as $note)
                    <li class="flex gap-2">
                        <flux:icon :name="match ($note['tone']) { 'good' => 'check-circle', 'warning' => 'exclamation-triangle', default => 'information-circle' }"
                                   @class(['mt-0.5 size-4 shrink-0', 'text-woka' => $note['tone'] === 'good', 'text-kunyit-700 dark:text-kunyit' => $note['tone'] === 'warning', 'text-hutan/50 dark:text-santan/50' => $note['tone'] === 'info']) />
                        <span>{{ $note['text'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Kehadiran --}}
        <h2 class="{{ $section }}">Kehadiran</h2>
        <div class="grid gap-3 lg:grid-cols-2">
            <div class="{{ $panel }}">
                <p class="mb-2 font-semibold">Kehadiran harian (%)</p>
                @if ($charts['attendance']['labels'] === [])
                    <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada hari absen pada rentang ini.</p>
                @else
                    <x-chart class="h-56" label="Grafik kehadiran harian" :config="[
                        'type' => 'line', 'palette' => 'validated', 'max' => 100, 'unit' => '%',
                        'data' => ['labels' => $charts['attendance']['labels'], 'datasets' => [
                            ['label' => 'Kehadiran', 'data' => $charts['attendance']['values'], 'fill' => true],
                            ['label' => 'Target '.angka($data['target']).'%', 'data' => array_fill(0, count($charts['attendance']['labels']), $data['target']), 'reference' => true],
                        ]],
                    ]" />
                @endif
            </div>

            <div class="{{ $panel }}">
                <p class="mb-1 font-semibold">Sebaran status absen harian</p>
                <p class="mb-3 text-xs text-hutan/50 dark:text-santan/50">{{ angka($data['statusExpected']) }} kewajiban absen (peserta × hari)</p>
                @php($total = max(1, $data['statusExpected']))
                <div class="mb-3 flex h-4 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Sebaran status absen">
                    @foreach ($statusColors as $key => $color)
                        @if ($data['status'][$key] > 0)
                            <span style="width: {{ $data['status'][$key] / $total * 100 }}%; background: {{ $color }}"></span>
                        @endif
                    @endforeach
                </div>
                <ul class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-2">
                    @foreach ($statusColors as $key => $color)
                        <li class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                            <span class="min-w-0 flex-1 truncate">{{ AttendanceAnalytics::label($key) }}</span>
                            <span class="tabular-nums text-hutan/70 dark:text-santan/70">{{ angka($data['status'][$key]) }} · {{ angka($data['status'][$key] / $total * 100) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (count($charts['classes']) > 0)
                <div class="{{ $panel }} lg:col-span-2">
                    <p class="mb-2 font-semibold">Perbandingan antarkelas (skala 0–100)</p>
                    <x-chart class="h-64" label="Kehadiran dan nilai per kelas" :config="[
                        'type' => 'bar', 'palette' => 'validated', 'max' => 100,
                        'data' => ['labels' => array_column($charts['classes'], 'name'), 'datasets' => [
                            ['label' => 'Kehadiran harian (%)', 'data' => array_column($charts['classes'], 'attendance')],
                            ['label' => 'Kehadiran sesi (%)', 'data' => array_column($charts['classes'], 'sessions')],
                            ['label' => 'Rata-rata nilai', 'data' => array_column($charts['classes'], 'score')],
                        ]],
                    ]" />
                </div>
            @endif
        </div>

        {{-- Nilai --}}
        <h2 class="{{ $section }}">Nilai</h2>
        <div class="grid gap-3 lg:grid-cols-3">
            <div class="{{ $panel }} lg:col-span-3">
                <p class="mb-2 font-semibold">Tren rata-rata nilai per minggu</p>
                <x-chart class="h-56" label="Tren nilai mingguan" :config="[
                    'type' => 'line', 'palette' => 'validated', 'max' => 100,
                    'data' => ['labels' => $charts['weekly']['labels'], 'datasets' => [
                        ['label' => 'Rata-rata nilai', 'data' => $charts['weekly']['values']],
                        ['label' => 'Batas lulus '.angka($data['passing']), 'data' => array_fill(0, count($charts['weekly']['labels']), $data['passing']), 'reference' => true],
                    ]],
                ]" />
            </div>
            <div class="{{ $panel }} lg:col-span-2">
                <p class="mb-2 font-semibold">Rata-rata per sumber nilai</p>
                <x-chart class="h-48" label="Rata-rata nilai per sumber" :config="[
                    'type' => 'bar', 'palette' => 'validated', 'max' => 100, 'options' => ['indexAxis' => 'y'],
                    'data' => ['labels' => array_column($charts['sources'], 'label'), 'datasets' => [
                        ['label' => 'Rata-rata', 'data' => array_column($charts['sources'], 'value')],
                    ]],
                ]" />
            </div>
            <div class="{{ $panel }}">
                <p class="mb-2 font-semibold">Predikat peserta</p>
                <x-chart class="h-48" label="Jumlah peserta per predikat" :config="[
                    'type' => 'bar', 'palette' => 'validated',
                    'data' => ['labels' => array_keys($charts['grades']), 'datasets' => [
                        ['label' => 'Peserta', 'data' => array_values($charts['grades'])],
                    ]],
                ]" />
            </div>
        </div>

        {{-- Konsumsi --}}
        <h2 class="{{ $section }}">Konsumsi</h2>
        <div class="grid gap-3 lg:grid-cols-3">
            <div class="{{ $panel }} lg:col-span-2">
                <p class="mb-2 font-semibold">Porsi per hari: dipesan, diterima dari vendor, dan tanda terima peserta</p>
                @if ($charts['meals']['labels'] === [])
                    <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada jadwal makan pada rentang ini.</p>
                @else
                    <x-chart class="h-60" label="Porsi konsumsi per hari" :config="[
                        'type' => 'bar', 'palette' => 'validated',
                        'data' => ['labels' => $charts['meals']['labels'], 'datasets' => [
                            ['label' => 'Dipesan', 'data' => $charts['meals']['ordered']],
                            ['label' => 'Diterima dari vendor', 'data' => $charts['meals']['delivered']],
                            ['label' => 'Tanda terima peserta', 'data' => $charts['meals']['received']],
                        ]],
                    ]" />
                @endif
            </div>
            <div class="{{ $panel }}">
                <p class="mb-2 font-semibold">Serapan per jenis (%)</p>
                @if ($charts['mealTypes'] === [])
                    <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada data.</p>
                @else
                    <x-chart class="h-60" label="Serapan konsumsi per jenis" :config="[
                        'type' => 'bar', 'palette' => 'validated', 'max' => 100, 'unit' => '%', 'options' => ['indexAxis' => 'y'],
                        'data' => ['labels' => array_column($charts['mealTypes'], 'label'), 'datasets' => [
                            ['label' => 'Serapan', 'data' => array_column($charts['mealTypes'], 'rate')],
                        ]],
                    ]" />
                @endif
            </div>
        </div>

        @if ($data['vendors'] !== [])
            <div class="mt-3 overflow-x-auto rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 text-left text-xs uppercase tracking-wide text-hutan/50 dark:border-hutan-800 dark:text-santan/50">
                            <th class="px-3 py-2">Vendor</th><th class="px-3 py-2 text-right">Jadwal</th><th class="px-3 py-2 text-right">Diterima dari vendor</th><th class="px-3 py-2 text-right">Tanda terima</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-hutan-800">
                        @foreach ($data['vendors'] as $vendor)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2 font-semibold">{{ $vendor['name'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ angka($vendor['schedules']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ angka($vendor['delivered']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ angka($vendor['received']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Peserta --}}
        <h2 class="{{ $section }}" id="peserta">Peserta</h2>

        @if ($detail)
            <div class="{{ $panel }} mb-3" data-test="exec-participant">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold">{{ $detail['name'] }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $detail['class'] }}</p>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="showParticipant(null)" aria-label="Tutup" />
                </div>
                <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ([
                        ['Kehadiran harian', $pct($detail['attendance'])],
                        ['Rata-rata nilai', $num($detail['overall']).($detail['grade'] ? ' ('.$detail['grade'].')' : '')],
                        ['Tanda terima konsumsi', angka($detail['meals'])],
                        ['Poin periode ini', angka($detail['points'])],
                    ] as [$label, $value])
                        <div class="rounded-xl bg-zinc-50 px-3 py-2 dark:bg-hutan-800">
                            <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $label }}</p>
                            <p class="font-display text-lg font-bold tabular-nums">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="grid gap-3 lg:grid-cols-2">
                    <div>
                        <p class="mb-2 text-sm font-semibold">Kehadiran per minggu (%)</p>
                        <x-chart class="h-48" label="Kehadiran peserta per minggu" :config="[
                            'type' => 'bar', 'palette' => 'validated', 'max' => 100, 'unit' => '%',
                            'data' => ['labels' => array_column($detail['weeks'], 'label'), 'datasets' => [
                                ['label' => 'Kehadiran', 'data' => array_column($detail['weeks'], 'attendance')],
                            ]],
                        ]" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-semibold">Nilai per minggu dibanding rata-rata kelas</p>
                        <x-chart class="h-48" label="Nilai peserta per minggu" :config="[
                            'type' => 'line', 'palette' => 'validated', 'max' => 100,
                            'data' => ['labels' => array_column($detail['weeks'], 'label'), 'datasets' => [
                                ['label' => 'Peserta', 'data' => array_column($detail['weeks'], 'score')],
                                ['label' => 'Rata-rata kelas', 'data' => array_column($detail['weeks'], 'class')],
                            ]],
                        ]" />
                    </div>
                </div>
                <p class="mt-3 text-xs text-hutan/60 dark:text-santan/60">
                    @foreach ($detail['scores'] as $source){{ $source['label'] }}: {{ $num($source['value']) }}@if (! $loop->last) · @endif @endforeach
                    · Terlambat {{ $detail['counts']['late'] }} · Izin {{ $detail['counts']['excused'] }} · Sakit {{ $detail['counts']['sick'] }} · Tidak absen {{ $detail['counts']['missing'] + $detail['counts']['absent'] }}
                </p>
            </div>
        @endif

        @if ($attention->isNotEmpty())
            <div class="mb-3 rounded-2xl border-s-4 border-kunyit bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800" data-test="exec-attention">
                <p class="mb-2 font-semibold">Perlu perhatian ({{ $attention->count() }})</p>
                <ul class="divide-y divide-zinc-100 text-sm dark:divide-hutan-800">
                    @foreach ($attention->take(10) as $person)
                        <li class="flex items-center gap-3 py-2" wire:key="at-{{ $person['id'] }}">
                            <button type="button" wire:click="showParticipant({{ $person['id'] }})" class="min-w-0 flex-1 text-left">
                                <span class="block truncate font-semibold hover:underline">{{ $person['name'] }}</span>
                                <span class="block truncate text-xs text-hutan/60 dark:text-santan/60">{{ implode(' · ', $person['flags']) }}</span>
                            </button>
                            <span class="shrink-0 text-xs tabular-nums text-hutan/70 dark:text-santan/70">{{ $pct($person['attendance']) }} · {{ $num($person['score']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari peserta" />
        </div>
        <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
            <table class="min-w-full text-sm" data-test="exec-people">
                <thead>
                    <tr class="border-b border-zinc-100 text-left text-xs uppercase tracking-wide text-hutan/50 dark:border-hutan-800 dark:text-santan/50">
                        <th class="px-3 py-2">Peserta</th>
                        <th class="px-3 py-2">Kehadiran</th>
                        <th class="px-3 py-2 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-hutan-800">
                    @forelse ($people as $person)
                        <tr wire:key="pp-{{ $person['id'] }}" @class(['bg-woka-50/60 dark:bg-hutan-800' => $participantId === $person['id']])>
                            <td class="px-3 py-2">
                                <button type="button" wire:click="showParticipant({{ $person['id'] }})" class="text-left">
                                    <span class="block font-semibold hover:underline">{{ $person['name'] }}</span>
                                    <span class="block text-xs text-hutan/60 dark:text-santan/60">{{ $person['class'] }}</span>
                                </button>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="hidden h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-zinc-100 sm:block dark:bg-hutan-800">
                                        <span class="block h-full rounded-full bg-woka" style="width: {{ min(100, (float) $person['attendance']) }}%"></span>
                                    </span>
                                    <span class="tabular-nums">{{ $pct($person['attendance']) }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                {{ $num($person['score']) }}
                                @if ($person['grade'])<flux:badge size="sm" :color="\App\Services\GradeService::color($person['grade'])" class="ms-1">{{ $person['grade'] }}</flux:badge>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-hutan/60 dark:text-santan/60">Tidak ada peserta pada cakupan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
