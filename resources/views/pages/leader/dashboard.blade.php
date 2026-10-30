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
    /** Tab grafik: ringkasan, kehadiran, nilai, konsumsi, peserta. */
    public const TABS = ['ringkasan' => ['Ringkasan', 'squares-2x2'], 'kehadiran' => ['Kehadiran', 'calendar-days'], 'nilai' => ['Nilai', 'chart-bar'], 'konsumsi' => ['Konsumsi', 'cake'], 'peserta' => ['Peserta', 'user-group']];

    /** Kolom tabel peserta yang bisa diurutkan. */
    public const SORTS = ['risk', 'name', 'attendance', 'late', 'absent', 'score'];

    #[Url(as: 'angkatan')]
    public ?int $batchId = null;

    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    #[Url(as: 'tab')]
    public string $tab = 'ringkasan';

    #[Url(as: 'peserta')]
    public ?int $participantId = null;

    public string $search = '';

    #[Url(as: 'urut')]
    public string $sort = 'risk';

    #[Url(as: 'arah')]
    public string $direction = 'asc';

    /** all, attention, low_attendance, low_score, late */
    #[Url(as: 'tampil')]
    public string $show = 'all';

    /** A, B, C, D, none (belum dinilai), atau kosong = semua */
    #[Url(as: 'predikat')]
    public string $grade = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('dashboard.leader'), 403);
        $this->batchId ??= Batch::current()?->id;
        if ($this->from === '' || $this->to === '') {
            $this->preset(14);
        }
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'ringkasan';
        }
        if (! in_array($this->sort, self::SORTS, true)) {
            $this->sort = 'risk';
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

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'ringkasan';
    }

    /** Detail peserta selalu dibuka di tab Peserta. */
    public function showParticipant(?int $id): void
    {
        $this->participantId = $id;
        if ($id) {
            $this->tab = 'peserta';
        }
    }

    /** Klik judul kolom: urutkan; klik lagi untuk membalik arah. */
    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTS, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            // Angka "buruk" lebih dulu: kehadiran & nilai terendah, terlambat & tidak absen terbanyak.
            $this->direction = in_array($column, ['late', 'absent'], true) ? 'desc' : 'asc';
        }
    }

    /** Dari ringkasan/grafik: buka tab Peserta dengan filter tertentu. */
    public function showPeople(string $show = 'all', string $grade = ''): void
    {
        $this->show = in_array($show, ['all', 'attention', 'low_attendance', 'low_score', 'late'], true) ? $show : 'all';
        $this->grade = in_array($grade, ['A', 'B', 'C', 'D', 'none'], true) ? $grade : '';
        $this->participantId = null;
        $this->tab = 'peserta';
    }

    public function resetPeopleFilters(): void
    {
        $this->reset('search', 'show', 'grade', 'sort', 'direction');
    }

    protected function filter(): ReportFilter
    {
        return ReportFilter::make(CarbonImmutable::parse($this->from ?: today()), CarbonImmutable::parse($this->to ?: today()), $this->batchId, $this->classroomId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function people(array $data)
    {
        $search = mb_strtolower(trim($this->search));
        $people = collect($data['people'])
            ->when($search !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['name'].' '.$p['class']), $search)))
            ->when($this->show === 'attention', fn ($c) => $c->filter(fn ($p) => $p['flags'] !== []))
            ->when($this->show === 'low_attendance', fn ($c) => $c->filter(fn ($p) => $p['attendance'] !== null && $p['attendance'] < $data['target']))
            ->when($this->show === 'low_score', fn ($c) => $c->filter(fn ($p) => $p['score'] !== null && $p['score'] < $data['passing']))
            ->when($this->show === 'late', fn ($c) => $c->filter(fn ($p) => $p['late'] >= 3))
            ->when($this->grade === 'none', fn ($c) => $c->filter(fn ($p) => $p['grade'] === null))
            ->when(in_array($this->grade, ['A', 'B', 'C', 'D'], true), fn ($c) => $c->filter(fn ($p) => $p['grade'] === $this->grade));

        // Nilai kosong selalu di akhir, ke mana pun arah urutannya.
        $key = match ($this->sort) {
            'name' => fn ($p) => mb_strtolower($p['name']),
            'attendance' => fn ($p) => $p['attendance'],
            'late' => fn ($p) => $p['late'],
            'absent' => fn ($p) => $p['absent'] ?? 0,
            'score' => fn ($p) => $p['score'],
            default => fn ($p) => [-count($p['flags']), $p['attendance'] ?? 101, $p['name']],
        };
        [$filled, $empty] = $people->partition(fn ($p) => $key($p) !== null);
        $sorted = $this->direction === 'desc' ? $filled->sortByDesc($key) : $filled->sortBy($key);

        return $sorted->concat($empty)->values();
    }

    public function with(ExecutiveDashboard $dashboard): array
    {
        $filter = $this->filter();
        $data = $dashboard->overview($filter);

        return [
            'filter' => $filter,
            'data' => $data,
            'people' => $this->tab === 'peserta' ? $this->people($data) : collect(),
            'attention' => collect($data['people'])->filter(fn ($p) => $p['flags'] !== [])->sortBy(fn ($p) => [-count($p['flags']), $p['attendance'] ?? 101])->values(),
            'detail' => $this->tab === 'peserta' && $this->participantId ? $dashboard->participant($filter, $this->participantId) : null,
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
        $section = 'mb-2 mt-6 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45';
        $panel = 'rounded-2xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800';
    @endphp

    <x-page-header title="Dashboard eksekutif" :subtitle="tanggal($filter->from, 'j M Y').' – '.tanggal($filter->to, 'j M Y').' · data diperbarui tiap 5 menit'">
        <x-slot:actions>
            <flux:button icon="document-arrow-down" :href="route('reports.index')" wire:navigate>Pusat laporan</flux:button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter dalam satu baris di atas semua grafik --}}
    <div class="{{ $panel }} mb-4 space-y-3">
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

    {{-- Tab grafik --}}
    <div class="-mx-4 mb-4 px-4">
        <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Bagian dashboard">
            @foreach ($this::TABS as $key => [$label, $icon])
                <button type="button" role="tab" wire:click="setTab('{{ $key }}')" wire:key="tab-{{ $key }}-{{ $tab }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-test="tab-{{ $key }}"
                        @if ($tab === $key) x-init="$el.parentElement.scrollLeft = $el.offsetLeft - 16" @endif
                        @class([
                            'flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold ring-1 transition',
                            'bg-woka text-white ring-woka' => $tab === $key,
                            'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $tab !== $key,
                        ])>
                    <flux:icon :name="$icon" class="size-4" /> {{ $label }}
                    @if ($key === 'peserta' && $attention->isNotEmpty())
                        <span @class(['rounded-full px-1.5 text-xs tabular-nums', 'bg-white/25' => $tab === $key, 'bg-kunyit/30 text-hutan dark:text-santan' => $tab !== $key])>{{ $attention->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="transition" role="tabpanel">
        @if ($tab === 'ringkasan')
            {{-- Angka utama --}}
            <div class="grid grid-cols-2 gap-2 lg:grid-cols-3 xl:grid-cols-6" data-test="exec-kpis">
                @foreach ([
                    ['Peserta aktif', angka($data['kpis']['participants']), null, 'peserta'],
                    ['Kehadiran harian', $pct($data['kpis']['attendance']), 'target '.angka($data['target']).'%', 'kehadiran'],
                    ['Kehadiran sesi', $pct($data['kpis']['sessions']), $data['kpis']['sessionCount'].' sesi', 'kehadiran'],
                    ['Rata-rata nilai', $num($data['kpis']['score']), 'batas lulus '.angka($data['passing']), 'nilai'],
                    ['Serapan konsumsi', $pct($data['kpis']['meals']), angka($data['kpis']['received']).' tanda terima', 'konsumsi'],
                    ['Porsi tak diambil', angka($data['kpis']['leftover']), 'diterima vendor − tanda terima', 'konsumsi'],
                ] as [$label, $value, $hint, $target])
                    <button type="button" wire:click="setTab('{{ $target }}')" class="rounded-2xl bg-white px-4 py-3 text-left ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800">
                        <span class="block truncate text-xs text-hutan/60 dark:text-santan/60">{{ $label }}</span>
                        <span class="block font-display text-2xl font-extrabold tabular-nums">{{ $value }}</span>
                        @if ($hint)<span class="block truncate text-xs text-hutan/50 dark:text-santan/50">{{ $hint }}</span>@endif
                    </button>
                @endforeach
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @if ($data['insights'] !== [])
                    <div class="{{ $panel }}">
                        <p class="mb-2 font-semibold">Catatan untuk keputusan</p>
                        <ul class="space-y-2 text-sm" data-test="exec-insights">
                            @foreach ($data['insights'] as $note)
                                <li class="flex gap-2">
                                    <flux:icon :name="match ($note['tone']) { 'good' => 'check-circle', 'warning' => 'exclamation-triangle', default => 'information-circle' }"
                                               @class(['mt-0.5 size-4 shrink-0', 'text-woka' => $note['tone'] === 'good', 'text-kunyit-700 dark:text-kunyit' => $note['tone'] === 'warning', 'text-hutan/50 dark:text-santan/50' => $note['tone'] === 'info']) />
                                    <span>{{ $note['text'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($attention->isNotEmpty())
                    <div class="rounded-2xl border-s-4 border-kunyit bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800" data-test="exec-attention">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="font-semibold">Perlu perhatian ({{ $attention->count() }})</p>
                            <flux:button size="xs" variant="ghost" wire:click="showPeople('attention')">Lihat semua</flux:button>
                        </div>
                        <ul class="divide-y divide-zinc-100 text-sm dark:divide-hutan-800">
                            @foreach ($attention->take(6) as $person)
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
            </div>
        @endif

        @if ($tab === 'kehadiran')
            <div class="grid gap-3 lg:grid-cols-2">
                <div class="{{ $panel }}">
                    <p class="mb-2 font-semibold">Kehadiran harian (%)</p>
                    @if ($charts['attendance']['labels'] === [])
                        <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada hari absen pada rentang ini.</p>
                    @else
                        <x-chart class="h-64" label="Grafik kehadiran harian" :config="[
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
                    @php $total = max(1, $data['statusExpected']); @endphp
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
                        <x-chart class="h-72" label="Kehadiran dan nilai per kelas" :config="[
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
        @endif

        @if ($tab === 'nilai')
            <div class="grid gap-3 lg:grid-cols-3">
                <div class="{{ $panel }} lg:col-span-3">
                    <p class="mb-2 font-semibold">Tren rata-rata nilai per minggu</p>
                    <x-chart class="h-64" label="Tren nilai mingguan" :config="[
                        'type' => 'line', 'palette' => 'validated', 'max' => 100,
                        'data' => ['labels' => $charts['weekly']['labels'], 'datasets' => [
                            ['label' => 'Rata-rata nilai', 'data' => $charts['weekly']['values']],
                            ['label' => 'Batas lulus '.angka($data['passing']), 'data' => array_fill(0, count($charts['weekly']['labels']), $data['passing']), 'reference' => true],
                        ]],
                    ]" />
                </div>
                <div class="{{ $panel }} lg:col-span-2">
                    <p class="mb-2 font-semibold">Rata-rata per sumber nilai</p>
                    <x-chart class="h-56" label="Rata-rata nilai per sumber" :config="[
                        'type' => 'bar', 'palette' => 'validated', 'max' => 100, 'options' => ['indexAxis' => 'y'],
                        'data' => ['labels' => array_column($charts['sources'], 'label'), 'datasets' => [
                            ['label' => 'Rata-rata', 'data' => array_column($charts['sources'], 'value')],
                        ]],
                    ]" />
                </div>
                <div class="{{ $panel }}">
                    <p class="mb-2 font-semibold">Predikat peserta</p>
                    <x-chart class="h-56" label="Jumlah peserta per predikat" :config="[
                        'type' => 'bar', 'palette' => 'validated',
                        'data' => ['labels' => array_keys($charts['grades']), 'datasets' => [
                            ['label' => 'Peserta', 'data' => array_values($charts['grades'])],
                        ]],
                    ]" />
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($charts['grades'] as $letter => $count)
                            <button type="button" wire:click="showPeople('all', '{{ $letter }}')" class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-zinc-200 hover:ring-woka-200 dark:ring-hutan-800">{{ $letter }}: {{ $count }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if ($tab === 'konsumsi')
            <div class="grid gap-3 lg:grid-cols-3">
                <div class="{{ $panel }} lg:col-span-2">
                    <p class="mb-2 font-semibold">Porsi per hari: dipesan, diterima dari vendor, dan tanda terima</p>
                    @if ($charts['meals']['labels'] === [])
                        <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada jadwal makan pada rentang ini.</p>
                    @else
                        <x-chart class="h-64" label="Porsi konsumsi per hari" :config="[
                            'type' => 'bar', 'palette' => 'validated',
                            'data' => ['labels' => $charts['meals']['labels'], 'datasets' => [
                                ['label' => 'Dipesan', 'data' => $charts['meals']['ordered']],
                                ['label' => 'Diterima dari vendor', 'data' => $charts['meals']['delivered']],
                                ['label' => 'Tanda terima', 'data' => $charts['meals']['received']],
                            ]],
                        ]" />
                    @endif
                </div>
                <div class="{{ $panel }}">
                    <p class="mb-2 font-semibold">Serapan per jenis (%)</p>
                    @if ($charts['mealTypes'] === [])
                        <p class="py-10 text-center text-sm text-hutan/50 dark:text-santan/50">Belum ada data.</p>
                    @else
                        <x-chart class="h-64" label="Serapan konsumsi per jenis" :config="[
                            'type' => 'bar', 'palette' => 'validated', 'max' => 100, 'unit' => '%', 'options' => ['indexAxis' => 'y'],
                            'data' => ['labels' => array_column($charts['mealTypes'], 'label'), 'datasets' => [
                                ['label' => 'Serapan', 'data' => array_column($charts['mealTypes'], 'rate')],
                            ]],
                        ]" />
                    @endif
                </div>
            </div>

            @if ($data['vendors'] !== [])
                <h2 class="{{ $section }}">Per vendor</h2>
                <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
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
        @endif

        @if ($tab === 'peserta')
            @if ($detail)
                <div class="{{ $panel }} mb-4" data-test="exec-participant">
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

            {{-- Filter tabel peserta --}}
            <div class="mb-3 grid gap-2 sm:grid-cols-3 [&>*]:min-w-0">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau kelas" aria-label="Cari peserta" />
                <flux:select wire:model.live="show" aria-label="Tampilkan">
                    <flux:select.option value="all">Semua peserta</flux:select.option>
                    <flux:select.option value="attention">Perlu perhatian</flux:select.option>
                    <flux:select.option value="low_attendance">Kehadiran &lt; target {{ angka($data['target']) }}%</flux:select.option>
                    <flux:select.option value="low_score">Nilai &lt; batas lulus {{ angka($data['passing']) }}</flux:select.option>
                    <flux:select.option value="late">Sering terlambat (≥ 3×)</flux:select.option>
                </flux:select>
                <flux:select wire:model.live="grade" aria-label="Predikat">
                    <flux:select.option value="">Semua predikat</flux:select.option>
                    @foreach (['A', 'B', 'C', 'D'] as $letter)
                        <flux:select.option :value="$letter">Predikat {{ $letter }}</flux:select.option>
                    @endforeach
                    <flux:select.option value="none">Belum dinilai</flux:select.option>
                </flux:select>
            </div>
            <div class="mb-2 flex items-center justify-between gap-2 text-xs text-hutan/60 dark:text-santan/60">
                <span data-test="people-count">{{ $people->count() }} dari {{ count($data['people']) }} peserta</span>
                @if ($search !== '' || $show !== 'all' || $grade !== '' || $sort !== 'risk')
                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="resetPeopleFilters">Reset</flux:button>
                @endif
            </div>

            @php
                $sortable = [
                    'name' => ['Peserta', 'text-left'],
                    'attendance' => ['Kehadiran', 'text-left'],
                    'late' => ['Terlambat', 'text-right'],
                    'absent' => ['Tidak absen', 'text-right'],
                    'score' => ['Nilai', 'text-right'],
                    'risk' => ['Status', 'text-left'],
                ];
            @endphp
            <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                <table class="min-w-full text-sm" data-test="exec-people">
                    <thead>
                        <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-hutan/50 dark:border-hutan-800 dark:text-santan/50">
                            @foreach ($sortable as $column => [$label, $align])
                                <th class="{{ $align }} whitespace-nowrap px-3 py-2 font-semibold"
                                    aria-sort="{{ $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                    <button type="button" wire:click="sortBy('{{ $column }}')" data-test="sort-{{ $column }}"
                                            @class(['inline-flex min-h-8 items-center gap-1 uppercase hover:text-woka', 'text-woka dark:text-woka-light' => $sort === $column])>
                                        {{ $label }}
                                        @if ($sort === $column)
                                            <flux:icon :name="$direction === 'asc' ? 'chevron-up' : 'chevron-down'" class="size-3.5" />
                                        @else
                                            <flux:icon.chevron-up-down class="size-3.5 opacity-40" />
                                        @endif
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-hutan-800">
                        @forelse ($people as $person)
                            <tr wire:key="pp-{{ $person['id'] }}" @class(['bg-woka-50/60 dark:bg-hutan-800' => $participantId === $person['id']])>
                                <td class="px-3 py-2">
                                    <button type="button" wire:click="showParticipant({{ $person['id'] }})" class="text-left">
                                        <span class="block whitespace-nowrap font-semibold hover:underline">{{ $person['name'] }}</span>
                                        <span class="block whitespace-nowrap text-xs text-hutan/60 dark:text-santan/60">{{ $person['class'] }}</span>
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="hidden h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-zinc-100 sm:block dark:bg-hutan-800">
                                            <span @class(['block h-full rounded-full', 'bg-woka' => ($person['attendance'] ?? 0) >= $data['target'], 'bg-rica' => ($person['attendance'] ?? 0) < $data['target']]) style="width: {{ min(100, (float) $person['attendance']) }}%"></span>
                                        </span>
                                        <span class="tabular-nums">{{ $pct($person['attendance']) }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $person['late'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $person['absent'] ?? 0 }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                    {{ $num($person['score']) }}
                                    @if ($person['grade'])<flux:badge size="sm" :color="\App\Services\GradeService::color($person['grade'])" class="ms-1">{{ $person['grade'] }}</flux:badge>@endif
                                </td>
                                <td class="px-3 py-2">
                                    @forelse ($person['flags'] as $flag)
                                        <flux:badge size="sm" color="amber" class="mb-0.5 me-0.5 whitespace-nowrap">{{ $flag }}</flux:badge>
                                    @empty
                                        <span class="text-xs text-woka dark:text-woka-light">Baik</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-hutan/60 dark:text-santan/60">Tidak ada peserta yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
