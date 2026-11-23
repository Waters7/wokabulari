<?php

use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Exports\ReportExport;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Services\Reports\QuizCombination;
use App\Services\Reports\ReportCard;
use App\Services\Reports\ScoreAnalytics;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Rekap & rapor nilai')] class extends Component {
    /** gabungan = gabungan beberapa quiz; rapor = semua penilaian per peserta */
    #[Url]
    public string $tab = 'gabungan';

    #[Url(as: 'kelas')]
    public ?int $classroom = null;

    /** @var list<string> */
    #[Url(as: 'quiz')]
    public array $quizIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('quiz.manage') || auth()->user()->can('session.view-all'), 403);
        $this->tab = in_array($this->tab, ['gabungan', 'rapor'], true) ? $this->tab : 'gabungan';
        $this->classroom = $this->currentClassroom()?->id ?? $this->classrooms()->first()?->id;

        if ($this->quizIds === []) {
            $this->preselect();
        }
    }

    /** Kelas yang boleh dilihat: semua (admin/pimpinan) atau kelas yang diajar. */
    protected function classrooms(): Collection
    {
        return Classroom::query()->active()->with(['language', 'batch'])->orderBy('name')->get()
            ->filter(fn (Classroom $c) => auth()->user()->can('viewScores', $c))->values();
    }

    protected function currentClassroom(): ?Classroom
    {
        return $this->classroom ? $this->classrooms()->firstWhere('id', $this->classroom) : null;
    }

    /** @return Collection<int, Quiz> quiz kelas yang sudah terbit/ditutup */
    protected function classQuizzes(Classroom $classroom): Collection
    {
        return Quiz::query()->where('classroom_id', $classroom->id)->where('status', '!=', QuizStatus::Draft)->orderBy('id')->get();
    }

    /** Pilihan awal: semua pretest kelas (pretest pilihan ganda + esai biasanya dipecah jadi beberapa quiz). */
    protected function preselect(): void
    {
        $classroom = $this->currentClassroom();
        if (! $classroom) {
            return;
        }

        $quizzes = $this->classQuizzes($classroom);
        $type = $quizzes->last(fn (Quiz $q) => in_array($q->type, [QuizType::Pretest, QuizType::Posttest], true))?->type;
        $this->quizIds = $type ? $quizzes->where('type', $type)->pluck('id')->map(fn ($id) => (string) $id)->values()->all() : [];
    }

    public function updatedClassroom(): void
    {
        $this->quizIds = [];
        $this->preselect();
    }

    public function selectType(string $type): void
    {
        $classroom = $this->currentClassroom();
        abort_unless($classroom, 404);
        $quizzes = $this->classQuizzes($classroom);
        $this->quizIds = ($type === 'semua' ? $quizzes : $quizzes->where('type', QuizType::tryFrom($type)))->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /** @return Collection<int, Quiz> */
    protected function selectedQuizzes(Classroom $classroom): Collection
    {
        return $this->classQuizzes($classroom)->whereIn('id', array_map('intval', $this->quizIds))->values();
    }

    /** Excel dari tab yang sedang dibuka. */
    public function export(QuizCombination $combiner, ReportCard $cards)
    {
        $classroom = $this->currentClassroom();
        abort_unless($classroom, 404);
        $this->authorize('viewScores', $classroom);

        if ($this->tab === 'gabungan') {
            $quizzes = $this->selectedQuizzes($classroom);
            abort_if($quizzes->isEmpty(), 422, 'Pilih quiz dulu.');
            $result = $combiner->combine($classroom, $quizzes);
            $report = [
                'title' => 'Rekap gabungan quiz',
                'period' => $quizzes->pluck('title')->implode(' + '),
                'sheets' => [[
                    'title' => 'Gabungan',
                    'headings' => ['Peringkat', 'Nama', 'NIS', ...$quizzes->map(fn (Quiz $q) => $q->title.' (maks '.poin($q->totalPoints()).')')->all(), 'Total poin', 'Poin maksimal', 'Nilai akhir', 'Predikat', 'Keterangan'],
                    'rows' => $result['rows']->map(fn (array $row) => [
                        $row['rank'], $row['user']->name, $row['user']->username,
                        ...$quizzes->map(fn (Quiz $q) => $row['parts'][$q->id]['score'])->all(),
                        $row['score'], $row['max'], $row['percentage'], $row['grade'],
                        trim(($row['missing'] ? $row['missing'].' bagian belum dikerjakan. ' : '').($row['pending'] ? $row['pending'].' bagian esai belum selesai dinilai.' : '')) ?: null,
                    ])->all(),
                ]],
            ];
        } else {
            $filter = $cards->filter($classroom);
            $report = [
                'title' => 'Rapor nilai peserta',
                'period' => tanggal($filter->from).' – '.tanggal($filter->to),
                'sheets' => [[
                    'title' => 'Rapor',
                    'headings' => ['No', 'Nama', 'NIS', 'Kehadiran harian (%)', ...array_values(ScoreAnalytics::SOURCES), 'Nilai akhir', 'Predikat'],
                    'rows' => $cards->summary($classroom)->values()->map(fn (array $row, int $i) => [
                        $i + 1, $row['user']->name, $row['user']->username, $row['attendance'],
                        ...array_map(fn ($source) => $row[$source], array_keys(ScoreAnalytics::SOURCES)), $row['overall'], $row['grade'],
                    ])->all(),
                ]],
            ];
        }

        activity('laporan')->causedBy(auth()->user())->withProperties(['jenis' => $report['title'], 'kelas' => $classroom->name])->log('Mengunduh laporan');

        return Excel::download(new ReportExport($report, [$classroom->label()]), Str::slug($report['title'].' '.$classroom->name).'-'.now()->format('Y-m-d').'.xlsx');
    }

    public function with(QuizCombination $combiner, ReportCard $cards): array
    {
        $classroom = $this->currentClassroom();
        $data = ['classrooms' => $this->classrooms(), 'current' => $classroom];

        if (! $classroom) {
            return $data;
        }

        if ($this->tab === 'gabungan') {
            $quizzes = $this->selectedQuizzes($classroom);

            return $data + [
                'allQuizzes' => $this->classQuizzes($classroom),
                'quizzes' => $quizzes,
                'result' => $quizzes->isNotEmpty() ? $combiner->combine($classroom, $quizzes) : null,
            ];
        }

        $filter = $cards->filter($classroom);

        return $data + ['summary' => $cards->summary($classroom), 'from' => $filter->from, 'to' => $filter->to];
    }
}; ?>

<div>
    <x-page-header title="Rekap & rapor nilai" subtitle="Gabungkan nilai beberapa quiz (mis. pretest pilihan ganda + esai) atau lihat rapor semua penilaian tiap peserta.">
        <x-slot:actions>
            @if ($current)
                <flux:button icon="arrow-down-tray" wire:click="export">Unduh Excel</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-2">
        <flux:radio.group wire:model.live="tab" variant="segmented" aria-label="Jenis rekap">
            <flux:radio value="gabungan" icon="squares-plus">Gabungan quiz</flux:radio>
            <flux:radio value="rapor" icon="document-text">Rapor peserta</flux:radio>
        </flux:radio.group>
        <flux:select wire:model.live="classroom" aria-label="Kelas">
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if (! $current)
        <x-empty-state icon="building-library" title="Belum ada kelas" />
    @elseif ($tab === 'gabungan')
        <x-card class="mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">Quiz yang digabung</p>
                <div class="flex flex-wrap gap-1">
                    @foreach (['pretest' => 'Semua pretest', 'posttest' => 'Semua posttest', 'semua' => 'Semua quiz'] as $type => $label)
                        <flux:button size="xs" variant="ghost" wire:click="selectType('{{ $type }}')">{{ $label }}</flux:button>
                    @endforeach
                </div>
            </div>
            @if ($allQuizzes->isEmpty())
                <p class="mt-2 text-sm text-hutan/60 dark:text-santan/60">Belum ada quiz yang terbit di kelas ini.</p>
            @else
                <div class="mt-2 grid gap-1.5 sm:grid-cols-2">
                    @foreach ($allQuizzes as $quiz)
                        <label wire:key="cq-{{ $quiz->id }}" class="flex cursor-pointer items-start gap-2 rounded-xl border border-woka-100 p-2.5 has-[:checked]:border-woka has-[:checked]:bg-woka-50 dark:border-hutan-800 dark:has-[:checked]:bg-hutan-800">
                            <input type="checkbox" value="{{ $quiz->id }}" wire:model.live="quizIds" class="mt-0.5 size-5 shrink-0 accent-woka">
                            <span class="min-w-0 text-sm">
                                <span class="block font-semibold">{{ $quiz->title }}</span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-hutan/60 dark:text-santan/60">
                                    <x-status-badge :status="$quiz->type" />
                                    {{ $quiz->opens_at ? tanggal($quiz->opens_at, 'j M Y') : 'Tanpa jadwal' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif
            <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Nilai akhir = jumlah poin semua quiz terpilih ÷ jumlah poin maksimal × 100. Quiz yang tidak dikerjakan bernilai 0; esai yang belum selesai dinilai memakai nilai sementara.</p>
        </x-card>

        @if ($result)
            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4" data-test="combined-summary">
                @foreach ([['Peserta', $result['rows']->count()], ['Rata-rata', $result['average'] !== null ? angka($result['average'], 1) : '–'], ['Tertinggi', $result['highest'] !== null ? angka($result['highest'], 1) : '–'], ['Lengkap dinilai', $result['complete'].'/'.$result['rows']->count()]] as [$label, $value])
                    <div class="rounded-2xl bg-white px-3 py-2.5 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                        <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $label }}</p>
                        <p class="font-display text-xl font-bold">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="table-scroll" data-test="combined-table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Peserta</th>
                            @foreach ($quizzes as $quiz)
                                <th class="text-right" title="{{ $quiz->title }}">{{ Str::limit($quiz->title, 22) }}<span class="block font-normal normal-case">maks {{ poin($quiz->totalPoints()) }}</span></th>
                            @endforeach
                            <th class="text-right">Total</th><th class="text-right">Nilai akhir</th><th>Predikat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['rows'] as $row)
                            <tr wire:key="cr-{{ $row['user']->id }}">
                                <td class="font-bold">{{ $row['rank'] }}</td>
                                <td class="whitespace-nowrap">
                                    <a href="{{ route('teach.report-card', [$current, $row['user']]) }}" wire:navigate class="font-semibold hover:underline">{{ $row['user']->name }}</a>
                                </td>
                                @foreach ($quizzes as $quiz)
                                    @php
                                        $part = $row['parts'][$quiz->id];
                                    @endphp
                                    <td class="whitespace-nowrap text-right tabular-nums">
                                        @if ($part['status'] === 'missing')
                                            <span class="text-xs text-rica">belum</span>
                                        @else
                                            {{ poin($part['score']) }}
                                            @if ($part['status'] === 'pending')<span class="block text-[10px] text-kunyit-700 dark:text-kunyit">esai belum dinilai</span>@endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="whitespace-nowrap text-right tabular-nums">{{ poin($row['score']) }}/{{ poin($row['max']) }}</td>
                                <td class="text-right font-display text-lg font-bold tabular-nums">{{ $row['percentage'] !== null ? angka($row['percentage'], 1) : '–' }}</td>
                                <td>@if ($row['grade'])<flux:badge size="sm" :color="\App\Services\GradeService::color($row['grade'])">{{ $row['grade'] }}</flux:badge>@else – @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state icon="squares-plus" title="Pilih quiz yang akan digabung" description="Centang dua quiz atau lebih, mis. pretest pilihan ganda dan pretest esai." />
        @endif
    @else
        <p class="mb-3 text-sm text-hutan/60 dark:text-santan/60">Periode {{ tanggal($from) }} – {{ tanggal($to) }} · nilai akhir = rata-rata nilai sesi, tugas, quiz, dan penilaian periodik yang sudah ada.</p>
        <div class="table-scroll" data-test="report-card-table">
            <table>
                <thead>
                    <tr>
                        <th>Peserta</th><th class="text-right">Hadir</th>
                        @foreach (ScoreAnalytics::SOURCES as $label)
                            <th class="text-right">{{ $label }}</th>
                        @endforeach
                        <th class="text-right">Nilai akhir</th><th>Predikat</th><th class="text-right">Rapor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary as $row)
                        <tr wire:key="rc-{{ $row['user']->id }}">
                            <td class="whitespace-nowrap font-semibold">{{ $row['user']->name }}</td>
                            <td class="text-right tabular-nums">{{ $row['attendance'] !== null ? angka($row['attendance'], 1).'%' : '–' }}</td>
                            @foreach (array_keys(ScoreAnalytics::SOURCES) as $source)
                                <td class="text-right tabular-nums">{{ $row[$source] !== null ? angka($row[$source], 1) : '–' }}</td>
                            @endforeach
                            <td class="text-right font-display text-lg font-bold tabular-nums">{{ $row['overall'] !== null ? angka($row['overall'], 1) : '–' }}</td>
                            <td>@if ($row['grade'])<flux:badge size="sm" :color="\App\Services\GradeService::color($row['grade'])">{{ $row['grade'] }}</flux:badge>@else – @endif</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('teach.report-card', [$current, $row['user']]) }}" wire:navigate class="font-semibold text-woka hover:underline dark:text-woka-light">Lihat</a>
                                · <a href="{{ route('teach.report-card.pdf', [$current, $row['user']]) }}" class="font-semibold text-woka hover:underline dark:text-woka-light">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-hutan/60 dark:text-santan/60">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
