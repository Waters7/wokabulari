<?php

use App\Enums\ReportPeriod;
use App\Enums\ReportType;
use App\Exports\AllReportsExport;
use App\Exports\ReportExport;
use App\Models\Batch;
use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use App\Support\Letterhead;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /** Tanggal akhir: data dipotong sampai tanggal ini (harian = tanggal itu saja). */
    #[Url(as: 'tanggal')]
    public string $date = '';

    /** Tanggal awal, khusus periode Rentang. */
    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'angkatan')]
    public ?int $batchId = null;

    #[Url(as: 'kelas')]
    public ?int $classroomId = null;

    /** Khusus laporan mengajar. */
    #[Url(as: 'pengajar')]
    public ?int $teacherId = null;

    /** Galeri dokumentasi sesi: '' = semua, 'missing' = belum lengkap. */
    #[Url(as: 'dok')]
    public string $docFilter = '';

    public int $galleryLimit = 24;

    public function mount(): void
    {
        $types = ReportType::for(auth()->user());
        abort_if($types === [], 403);

        $this->type = ReportType::tryFrom($this->type)?->allows(auth()->user()) ? $this->type : $types[0]->value;
        $this->date = $this->date ?: today()->toDateString();
        $this->from = $this->from ?: CarbonImmutable::parse($this->date)->startOfMonth()->toDateString();
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

    /** Panah sebelum/berikutnya: lompat ke periode lain, tanggal akhir = akhir periode itu (atau hari ini). */
    public function shift(int $steps): void
    {
        $period = ReportPeriod::from($this->period);

        if ($period === ReportPeriod::Range) {
            [$from, $to] = $this->window();
            $days = (int) $from->diffInDays($to) + 1;
            $this->from = $from->addDays($days * $steps)->toDateString();
            $this->date = $to->addDays($days * $steps)->toDateString();

            return;
        }

        $this->date = $period->shiftUntil($this->date, $steps)->toDateString();
    }

    /**
     * Rentang data: awal periode s.d. tanggal yang dipilih (data sesudahnya tidak diambil).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function window(): array
    {
        return ReportPeriod::from($this->period)->until($this->date, $this->from);
    }

    protected function report(ReportBuilder $builder, ?ReportType $type = null): array
    {
        $type ??= ReportType::from($this->type);
        abort_unless($type->allows(auth()->user()), 403);
        [$from, $to] = $this->window();

        return $builder->between($type, $from, $to, $this->batchId, $this->classroomId,
            $type === ReportType::Teaching ? $this->teacherId : null, ReportPeriod::from($this->period)->untilLabel($this->date, $this->from));
    }

    /** @return list<string> */
    protected function scope(?ReportType $type = null): array
    {
        $type ??= ReportType::from($this->type);

        if ($type === ReportType::MealDeliveries) {
            return ['Semua peserta'];
        }

        return array_values(array_filter([
            Batch::query()->find($this->batchId)?->name ?? 'Semua angkatan',
            Classroom::query()->find($this->classroomId)?->name,
            $type === ReportType::Teaching && $this->teacherId ? 'Pengajar: '.User::query()->find($this->teacherId)?->name : null,
        ]));
    }

    /** Buang lembar yang butuh izin tambahan (mis. foto dokumentasi kelas hanya untuk session.view-all). */
    protected function permitted(array $report): array
    {
        $report['sheets'] = array_values(array_filter($report['sheets'], fn (array $sheet) => ! isset($sheet['can']) || auth()->user()->can($sheet['can'])));

        return $report;
    }

    /** Akhiran nama berkas: "2026-11-04-harian" atau "2026-11-01-sd-2026-11-06". */
    protected function fileSuffix(): string
    {
        [$from, $to] = $this->window();

        return $from->eq($to) ? $from->format('Y-m-d').'-harian' : $from->format('Y-m-d').'-sd-'.$to->format('Y-m-d');
    }

    public function export(ReportBuilder $builder)
    {
        abort_unless(auth()->user()->can('report.export'), 403);
        $report = $this->permitted($this->report($builder));

        activity('laporan')->causedBy(auth()->user())
            ->withProperties(['jenis' => $report['title'], 'periode' => $report['period'], 'cakupan' => $this->scope()])
            ->log('Mengunduh laporan');

        return Excel::download(new ReportExport($report, $this->scope()), Str::slug($report['title']).'-'.$this->fileSuffix().'.xlsx');
    }

    /** PDF berkop (A4 mendatar): tabel yang sama dengan layar, kop Poltekkes dan waktu cetak di setiap halaman. */
    public function exportPdf(ReportBuilder $builder)
    {
        abort_unless(auth()->user()->can('report.export'), 403);
        @set_time_limit(300);

        $report = $this->permitted($this->report($builder));
        // Lembar khusus Excel (foto dokumentasi) tidak ikut di PDF.
        $report['sheets'] = array_values(array_filter($report['sheets'], fn (array $sheet) => empty($sheet['excel_only'])));

        activity('laporan')->causedBy(auth()->user())
            ->withProperties(['jenis' => $report['title'], 'periode' => $report['period'], 'cakupan' => $this->scope(), 'format' => 'PDF'])
            ->log('Mengunduh laporan');

        $pdf = Pdf::loadView('pdf.report', [
            'report' => $report,
            'scope' => $this->scope(),
            'kop' => Letterhead::pdf(),
            'printedAt' => now(),
            'printedBy' => auth()->user()->name,
        ])->setPaper('a4', 'landscape');
        $content = Letterhead::output($pdf);

        return response()->streamDownload(fn () => print ($content), Str::slug($report['title']).'-'.$this->fileSuffix().'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** Semua jenis laporan yang boleh dilihat pengguna, satu berkas Excel, untuk rentang & cakupan yang sama. */
    public function exportAll(ReportBuilder $builder)
    {
        abort_unless(auth()->user()->can('report.export'), 403);
        @set_time_limit(300);

        $parts = array_map(fn (ReportType $type) => [
            'type' => $type,
            'report' => $this->permitted($this->report($builder, $type)),
            'scope' => $this->scope($type),
        ], ReportType::for(auth()->user()));
        $period = ReportPeriod::from($this->period)->untilLabel($this->date, $this->from);

        activity('laporan')->causedBy(auth()->user())
            ->withProperties(['jenis' => 'Semua laporan', 'periode' => $period, 'cakupan' => $this->scope(ReportType::DailyAttendance)])
            ->log('Mengunduh laporan');

        return Excel::download(new AllReportsExport($parts, $period, $this->scope(ReportType::DailyAttendance)), 'semua-laporan-'.$this->fileSuffix().'.xlsx');
    }

    public function with(ReportBuilder $builder): array
    {
        $type = ReportType::from($this->type);

        return [
            'types' => ReportType::for(auth()->user()),
            'current' => $type,
            'periods' => $type->periods(),
            'currentPeriod' => ReportPeriod::from($this->period),
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
                <flux:button icon="document-text" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" data-test="export-pdf">
                    <span wire:loading.remove wire:target="exportPdf">Unduh PDF berkop</span>
                    <span wire:loading wire:target="exportPdf">Menyiapkan PDF…</span>
                </flux:button>
                <flux:button icon="archive-box-arrow-down" wire:click="exportAll" wire:loading.attr="disabled" wire:target="exportAll" data-test="export-all">
                    <span wire:loading.remove wire:target="exportAll">Unduh semua laporan</span>
                    <span wire:loading wire:target="exportAll">Menyiapkan…</span>
                </flux:button>
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
            <flux:radio.group wire:model.live="period" label="Periode" variant="segmented" class="w-full sm:w-auto">
                @foreach ($periods as $option)
                    <flux:radio :value="$option->value" :label="$option->label()" class="px-2! data-checked:bg-woka! data-checked:text-white! sm:px-4!" />
                @endforeach
            </flux:radio.group>
            @if ($currentPeriod === ReportPeriod::Range)
                <div class="grid w-full grid-cols-2 gap-2 sm:w-auto">
                    <div class="min-w-0 sm:w-40 [&_div]:min-w-0 [&_input]:min-w-0 [&_input]:px-2 sm:[&_input]:px-3"><flux:input wire:model.live="from" type="date" label="Dari" /></div>
                    <div class="min-w-0 sm:w-40 [&_div]:min-w-0 [&_input]:min-w-0 [&_input]:px-2 sm:[&_input]:px-3"><flux:input wire:model.live="date" type="date" label="Sampai" /></div>
                </div>
            @else
                <div class="flex items-end gap-1">
                    <flux:button variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Periode sebelumnya" />
                    <div class="w-40"><flux:input wire:model.live="date" type="date" :label="$currentPeriod === ReportPeriod::Daily ? 'Tanggal' : 'Sampai tanggal'" /></div>
                    <flux:button variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Periode berikutnya" />
                </div>
            @endif
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
        <p class="mb-3 text-sm text-hutan/60 dark:text-santan/60" data-test="report-period">
            {{ $report['period'] }}
            @if ($currentPeriod !== ReportPeriod::Daily)
                <span class="block text-xs">Data diambil sampai {{ tanggal($date) }}; tanggal sesudahnya tidak ikut.</span>
            @endif
        </p>

        <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($report['summary'] as $item)
                <div class="rounded-2xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                    <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $item['label'] }}</p>
                    <p class="font-display text-xl font-bold">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </div>

        @foreach (array_filter($report['sheets'], fn (array $sheet) => empty($sheet['excel_only'])) as $index => $sheet)
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
                                                @if (is_string($cell) && str_starts_with($cell, url('/').'/'))
                                                    <a href="{{ $cell }}" wire:navigate class="font-semibold text-woka hover:underline dark:text-woka-light">Buka sesi</a>
                                                @else
                                                    {{ $cell === null || $cell === '' ? '–' : (is_float($cell) ? angka($cell, 1) : $cell) }}
                                                @endif
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

            @if ($index === 0 && isset($report['gallery']))
                @include('partials.reports.session-gallery', ['gallery' => $report['gallery'], 'docFilter' => $docFilter, 'galleryLimit' => $galleryLimit])
            @endif
            @if ($index === 0 && isset($report['meal_gallery']))
                @include('partials.reports.meal-gallery', ['gallery' => $report['meal_gallery'], 'docFilter' => $docFilter, 'galleryLimit' => $galleryLimit])
            @endif
        @endforeach
    </div>
</div>
