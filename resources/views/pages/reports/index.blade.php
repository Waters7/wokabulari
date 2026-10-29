<?php

use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Models\Batch;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Pusat laporan')] class extends Component {
    /** Baris maksimal per tabel di layar; Excel memuat semua. */
    public const SCREEN_ROWS = 300;

    #[Url(as: 'jenis')]
    public string $type = '';

    #[Url(as: 'periode')]
    public string $period = 'daily';

    #[Url(as: 'tanggal')]
    public string $date = '';

    #[Url(as: 'angkatan')]
    public ?int $batchId = null;

    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    /** Khusus laporan mengajar. */
    #[Url(as: 'pengajar')]
    public ?int $teacherId = null;

    public function mount(): void
    {
        $types = ReportType::for(auth()->user());
        abort_if($types === [], 403);

        $this->type = ReportType::tryFrom($this->type)?->allows(auth()->user()) ? $this->type : $types[0]->value;
        $this->date = $this->date ?: today()->toDateString();
        $this->batchId ??= Batch::current()?->id;
        $this->normalizePeriod();
    }

    public function updatedType(): void
    {
        abort_unless(ReportType::from($this->type)->allows(auth()->user()), 403);
        $this->normalizePeriod();
    }

    public function updatedBatchId(): void
    {
        $this->classroomId = null;
    }

    protected function normalizePeriod(): void
    {
        $periods = ReportType::from($this->type)->periods();
        if (! in_array(ReportPeriod::tryFrom($this->period), $periods, true)) {
            $this->period = $periods[0]->value;
        }
    }

    public function shift(int $steps): void
    {
        $this->date = ReportPeriod::from($this->period)->shift($this->date, $steps)->toDateString();
    }

    protected function report(ReportBuilder $builder): array
    {
        $type = ReportType::from($this->type);
        abort_unless($type->allows(auth()->user()), 403);

        return $builder->build($type, ReportPeriod::from($this->period), CarbonImmutable::parse($this->date), $this->batchId, $this->classroomId,
            $type === ReportType::Teaching ? $this->teacherId : null);
    }

    /** @return list<string> */
    protected function scope(): array
    {
        if ($this->type === ReportType::MealDeliveries->value) {
            return ['Semua peserta'];
        }

        return array_values(array_filter([
            Batch::query()->find($this->batchId)?->name ?? 'Semua angkatan',
            Classroom::query()->find($this->classroomId)?->name,
            $this->type === ReportType::Teaching->value && $this->teacherId ? 'Pengajar: '.User::query()->find($this->teacherId)?->name : null,
        ]));
    }

    public function export(ReportBuilder $builder)
    {
        abort_unless(auth()->user()->can('report.export'), 403);
        $report = $this->report($builder);

        activity('laporan')->causedBy(auth()->user())
            ->withProperties(['jenis' => $report['title'], 'periode' => $report['period'], 'cakupan' => $this->scope()])
            ->log('Mengunduh laporan');

        $name = Str::slug($report['title']).'-'.ReportPeriod::from($this->period)->range($this->date)[0]->format('Y-m-d').'-'.Str::slug(ReportPeriod::from($this->period)->label()).'.xlsx';

        return Excel::download(new ReportExport($report, $this->scope()), $name);
    }

    public function with(ReportBuilder $builder): array
    {
        $type = ReportType::from($this->type);

        return [
            'types' => ReportType::for(auth()->user()),
            'current' => $type,
            'periods' => $type->periods(),
            'report' => $this->report($builder),
            'scoped' => $type !== ReportType::MealDeliveries,
            'batches' => Batch::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classrooms' => Classroom::query()->when($this->batchId, fn ($q) => $q->where('batch_id', $this->batchId))->orderBy('name')->get(['id', 'name']),
            'teachers' => $type === ReportType::Teaching ? User::query()->role(Role::Teacher->value)->orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Pusat laporan" subtitle="Pilih jenis laporan dan periode, lalu unduh Excel bila perlu.">
        <x-slot:actions>
            @can('report.export')
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="export">Unduh Excel</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist">
        @foreach ($types as $option)
            <button type="button" wire:click="$set('type', '{{ $option->value }}')" wire:key="rt-{{ $option->value }}" role="tab" aria-selected="{{ $current === $option ? 'true' : 'false' }}"
                    @class([
                        'flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold ring-1 transition',
                        'bg-woka text-white ring-woka' => $current === $option,
                        'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $current !== $option,
                    ])>
                <flux:icon :name="$option->icon()" class="size-4" /> {{ $option->label() }}
            </button>
        @endforeach
    </div>

    <x-card class="mb-4 space-y-3">
        <p class="text-sm text-hutan/70 dark:text-santan/70">{{ $current->description() }}</p>
        <div class="flex flex-wrap items-end gap-3">
            <flux:radio.group wire:model.live="period" label="Periode" variant="segmented">
                @foreach ($periods as $option)
                    <flux:radio :value="$option->value" :label="$option->label()" class="data-checked:bg-woka! data-checked:text-white!" />
                @endforeach
            </flux:radio.group>
            <div class="flex items-end gap-1">
                <flux:button variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Periode sebelumnya" />
                <flux:input wire:model.live="date" type="date" label="Tanggal" class="w-40" />
                <flux:button variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Periode berikutnya" />
            </div>
            @if ($scoped)
                <flux:select wire:model.live="batchId" label="Angkatan" placeholder="Semua angkatan" class="w-full sm:w-48">
                    @foreach ($batches as $batch)
                        <flux:select.option :value="$batch->id">{{ $batch->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="classroomId" label="Kelas" placeholder="Semua kelas" class="w-full sm:w-48">
                    <flux:select.option value="">Semua kelas</flux:select.option>
                    @foreach ($classrooms as $classroom)
                        <flux:select.option :value="$classroom->id">{{ $classroom->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if ($current === ReportType::Teaching)
                <flux:select wire:model.live="teacherId" label="Pengajar" class="w-full sm:w-48">
                    <flux:select.option value="">Semua pengajar</flux:select.option>
                    @foreach ($teachers as $teacher)
                        <flux:select.option :value="$teacher->id">{{ $teacher->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>
    </x-card>

    <div wire:loading.class="opacity-60" class="transition">
        <h2 class="font-display text-xl font-bold">{{ $report['title'] }}</h2>
        <p class="mb-3 text-sm text-hutan/60 dark:text-santan/60" data-test="report-period">{{ $report['period'] }}</p>

        <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($report['summary'] as $item)
                <div class="rounded-2xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                    <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $item['label'] }}</p>
                    <p class="font-display text-xl font-bold">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </div>

        @foreach ($report['sheets'] as $index => $sheet)
            <details @if ($index === 0) open @endif class="group mb-4" wire:key="rs-{{ $current->value }}-{{ $index }}">
                <summary class="mb-2 flex cursor-pointer list-none items-center gap-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">
                    <flux:icon.chevron-right class="size-4 transition group-open:rotate-90" />
                    {{ $sheet['title'] }} <span class="font-normal normal-case">({{ count($sheet['rows']) }} baris)</span>
                </summary>
                @if ($sheet['rows'] === [])
                    <p class="rounded-2xl bg-white px-4 py-6 text-center text-sm text-hutan/60 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:text-santan/60 dark:ring-hutan-800">Belum ada data pada periode ini.</p>
                @else
                    <div class="overflow-x-auto rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-left text-xs uppercase tracking-wide text-hutan/50 dark:border-hutan-800 dark:text-santan/50">
                                    @foreach ($sheet['headings'] as $heading)
                                        <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-hutan-800">
                                @foreach (array_slice($sheet['rows'], 0, $this::SCREEN_ROWS) as $row)
                                    <tr>
                                        @foreach ($row as $cell)
                                            <td @class(['whitespace-nowrap px-3 py-2', 'text-right tabular-nums' => is_int($cell) || is_float($cell)])>
                                                {{ $cell === null || $cell === '' ? '–' : (is_float($cell) ? angka($cell, 1) : $cell) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($sheet['rows']) > $this::SCREEN_ROWS)
                        <p class="mt-1 text-xs text-hutan/50 dark:text-santan/50">Menampilkan {{ $this::SCREEN_ROWS }} baris pertama. Unduh Excel untuk semua baris.</p>
                    @endif
                @endif
            </details>
        @endforeach
    </div>
</div>
