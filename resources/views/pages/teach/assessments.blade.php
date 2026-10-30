<?php

use App\Actions\Assessments\SaveAssessment;
use App\Actions\Assessments\SetClassroomAspects;
use App\Enums\PeriodType;
use App\Http\Requests\ClassroomAspectsRequest;
use App\Models\Assessment;
use App\Models\AssessmentAspect;
use App\Models\Classroom;
use App\Models\User;
use App\Services\AssessmentSuggester;
use App\Services\PeriodicAspects;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Penilaian periodik')] class extends Component {
    /** Filter status peserta pada periode ini. */
    public const STATES = ['' => 'Semua', 'empty' => 'Belum dinilai', 'draft' => 'Draf', 'published' => 'Terbit'];

    public const STATE_COLORS = ['empty' => 'zinc', 'draft' => 'amber', 'published' => 'green'];

    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $type = 'weekly';

    #[Url]
    public string $period = '';

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url]
    public string $status = '';

    /** @var array<int, array{scores: array<int, string>, feedback: string, visible_to_guardian: bool}> */
    public array $rows = [];

    /** @var array<int, array<int, float|null>> */
    public array $suggestions = [];

    /** @var list<int> Kolom aspek pada periode ini */
    public array $aspectIds = [];

    public bool $showAspects = false;

    /** @var list<string> */
    public array $chosenAspects = [];

    public function mount(): void
    {
        $this->classroom ??= $this->classrooms()->first()?->id;
        $this->period = $this->period ?: now()->toDateString();
        $this->status = array_key_exists($this->status, self::STATES) ? $this->status : '';
        $this->load();
    }

    protected function classrooms()
    {
        $user = auth()->user();

        return Classroom::query()->active()->with('language')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->taughtBy($user))
            ->orderBy('name')->get();
    }

    protected function currentClassroom(): ?Classroom
    {
        return $this->classroom ? Classroom::find($this->classroom) : null;
    }

    protected function periodType(): PeriodType
    {
        return PeriodType::tryFrom($this->type) ?? PeriodType::Weekly;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function range(): array
    {
        return $this->periodType()->range($this->period ?: now());
    }

    /** @return Collection<int, Assessment> penilaian periode ini, kunci = id peserta */
    protected function periodAssessments(Classroom $classroom): Collection
    {
        [$start] = $this->range();

        return Assessment::query()->where('classroom_id', $classroom->id)->where('period_type', $this->periodType())
            ->whereDate('period_start', $start)->with('scores')->get()->keyBy('user_id');
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['classroom', 'type', 'period'], true)) {
            $this->load();
        }
    }

    public function shift(int $delta): void
    {
        [$start] = $this->range();
        $this->period = ($this->periodType() === PeriodType::Weekly ? $start->addWeeks($delta) : $start->addMonths($delta))->toDateString();
        $this->load();
    }

    public function thisPeriod(): void
    {
        $this->period = now()->toDateString();
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
    }

    /**
     * Muat nilai periode ini. $keepInput = pertahankan isian yang belum disimpan (mis. setelah mengubah aspek).
     */
    protected function load(bool $keepInput = false): void
    {
        $previous = $keepInput ? $this->rows : [];
        $this->rows = [];
        $this->suggestions = [];
        $this->aspectIds = [];
        $classroom = $this->currentClassroom();

        if (! $classroom) {
            return;
        }

        $this->authorize('manage', [Assessment::class, $classroom]);
        [$start, $end] = $this->range();

        $existing = $this->periodAssessments($classroom);
        $aspects = app(PeriodicAspects::class)->forPeriod($classroom, $existing->pluck('id'));
        $this->aspectIds = $aspects->pluck('id')->all();
        $suggester = app(AssessmentSuggester::class);

        foreach ($classroom->participants()->orderBy('name')->get() as $participant) {
            $assessment = $existing->get($participant->id);
            $scores = [];
            foreach ($aspects as $aspect) {
                $value = $assessment?->scores->firstWhere('assessment_aspect_id', $aspect->id)?->score;
                $kept = $previous[$participant->id]['scores'][$aspect->id] ?? '';
                $scores[$aspect->id] = $kept !== '' ? $kept : ($value === null ? '' : (string) $value);
            }

            $this->rows[$participant->id] = [
                'scores' => $scores,
                'feedback' => $previous[$participant->id]['feedback'] ?? $assessment?->feedback ?? '',
                'visible_to_guardian' => $previous[$participant->id]['visible_to_guardian'] ?? $assessment?->visible_to_guardian ?? true,
            ];
            $this->suggestions[$participant->id] = $suggester->suggest($classroom, $participant->id, $start, $end, $aspects);
        }
    }

    /** Isi kolom kosong dengan saran dari komponen sesi, quiz, dan tugas pada periode ini. */
    public function fillSuggestions(): void
    {
        $filled = 0;
        foreach ($this->rows as $userId => $row) {
            foreach ($row['scores'] as $aspectId => $value) {
                $suggest = $this->suggestions[$userId][$aspectId] ?? null;
                if (trim((string) $value) === '' && $suggest !== null) {
                    $this->rows[$userId]['scores'][$aspectId] = (string) round($suggest);
                    $filled++;
                }
            }
        }

        Flux::toast(text: $filled > 0
            ? "{$filled} kolom kosong diisi saran nilai. Periksa dulu sebelum menyimpan."
            : 'Belum ada saran nilai: belum ada nilai sesi, quiz, atau tugas pada periode ini.');
    }

    /** @return list<Assessment> */
    protected function saveAll(SaveAssessment $save): array
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);
        $this->resetErrorBag();
        $existing = $this->periodAssessments($classroom);

        $saved = [];
        foreach ($this->rows as $userId => $row) {
            $scores = array_intersect_key($row['scores'] ?? [], array_flip($this->aspectIds));
            $filled = collect($scores)->contains(fn ($v) => trim((string) $v) !== '') || trim((string) ($row['feedback'] ?? '')) !== '';

            // Baris yang masih kosong tidak dibuat sebagai draf.
            if (! $filled && ! $existing->has($userId)) {
                continue;
            }

            try {
                $saved[] = $save->save($classroom, User::findOrFail($userId), auth()->user(), $this->periodType(), CarbonImmutable::parse($this->period), [
                    'scores' => $scores,
                    'feedback' => filled($row['feedback'] ?? null) ? trim($row['feedback']) : null,
                    'visible_to_guardian' => (bool) ($row['visible_to_guardian'] ?? true),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addError("rows.{$userId}", $e->getMessage());
            }
        }

        return $saved;
    }

    public function saveDraft(SaveAssessment $save): void
    {
        $saved = $this->saveAll($save);

        if ($this->getErrorBag()->isNotEmpty()) {
            Flux::toast(variant: 'danger', text: 'Ada nilai yang belum benar. Periksa kartu bertanda merah.');

            return;
        }

        Flux::toast(variant: 'success', text: count($saved).' penilaian tersimpan sebagai draf. Peserta belum bisa melihatnya.');
    }

    public function publish(SaveAssessment $save): void
    {
        $saved = $this->saveAll($save);

        if ($this->getErrorBag()->isNotEmpty()) {
            Flux::toast(variant: 'danger', text: 'Belum diterbitkan: ada nilai yang belum benar. Periksa kartu bertanda merah.');

            return;
        }

        $count = $save->publish($saved);
        Flux::toast(variant: 'success', text: $count > 0 ? "{$count} penilaian diterbitkan. Peserta mendapat notifikasi." : 'Tidak ada penilaian baru untuk diterbitkan.');
    }

    public function unpublish(int $userId, SaveAssessment $save): void
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);
        [$start] = $this->range();

        $assessment = Assessment::query()->where('classroom_id', $classroom->id)->where('user_id', $userId)
            ->where('period_type', $this->periodType())->whereDate('period_start', $start)->firstOrFail();
        $save->unpublish($assessment);
        Flux::toast(text: 'Dikembalikan ke draf.');
    }

    public function openAspects(): void
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);
        $this->resetErrorBag('chosenAspects');
        $this->chosenAspects = app(PeriodicAspects::class)->selected($classroom)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showAspects = true;
    }

    public function saveAspects(SetClassroomAspects $set): void
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);

        $validator = Validator::make(['aspects' => $this->chosenAspects], ClassroomAspectsRequest::rulesFor($classroom), ClassroomAspectsRequest::messagesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(['chosenAspects' => $validator->errors()->first()]);
        }

        $set->handle($classroom, $this->chosenAspects);
        $this->showAspects = false;
        $this->load(keepInput: true);
        Flux::toast(variant: 'success', text: 'Aspek penilaian kelas diperbarui.');
    }

    /** Kembali ke aspek bawaan dari master data. */
    public function resetAspects(SetClassroomAspects $set): void
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);

        $set->handle($classroom, []);
        $this->showAspects = false;
        $this->load(keepInput: true);
        Flux::toast(text: 'Kelas memakai aspek bawaan.');
    }

    public function with(): array
    {
        $classroom = $this->currentClassroom();
        [$start] = $this->range();
        $assessments = $classroom ? $this->periodAssessments($classroom) : collect();
        $participants = $classroom ? $classroom->participants()->orderBy('name')->get(['users.id', 'users.name', 'users.username']) : collect();

        $states = $participants->mapWithKeys(function (User $p) use ($assessments) {
            $assessment = $assessments->get($p->id);

            return [$p->id => match (true) {
                $assessment?->isPublished() => 'published',
                $assessment?->average !== null => 'draft',
                default => 'empty',
            }];
        });

        $term = mb_strtolower(trim($this->search));
        $visible = $participants->filter(fn (User $p) => ($this->status === '' || $states[$p->id] === $this->status)
            && ($term === '' || str_contains(mb_strtolower($p->name), $term) || str_contains(mb_strtolower((string) $p->username), $term)));

        $aspects = PeriodicAspects::sort(AssessmentAspect::query()->whereIn('id', $this->aspectIds)->get());
        $periodic = app(PeriodicAspects::class);

        return [
            'classrooms' => $this->classrooms(),
            'current' => $classroom,
            'participants' => $visible,
            'total' => $participants->count(),
            'counts' => collect(self::STATES)->keys()->mapWithKeys(fn ($s) => [$s => $s === '' ? $participants->count() : $states->filter(fn ($v) => $v === $s)->count()]),
            'states' => $states,
            'assessments' => $assessments,
            'aspects' => $aspects,
            'customized' => $classroom ? $periodic->isCustomized($classroom) : false,
            'available' => $classroom && $this->showAspects ? $periodic->available($classroom) : collect(),
            'periodLabel' => $this->periodType()->periodLabel($start),
            'isCurrentPeriod' => $start->equalTo($this->periodType()->range(now())[0]),
            'thresholds' => ['A' => (float) setting('grade.threshold_a'), 'B' => (float) setting('grade.threshold_b'), 'C' => (float) setting('grade.threshold_c')],
        ];
    }
}; ?>

<div>
    <x-page-header title="Penilaian periodik" subtitle="Nilai mingguan/bulanan per aspek. Peserta dan wali hanya melihat penilaian yang sudah terbit." />

    {{-- Langkah 1: kelas & periode --}}
    <x-card class="mb-3">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">1 · Kelas & periode</p>
        <div class="grid gap-2 sm:grid-cols-3">
            <flux:select wire:model.live="classroom" aria-label="Kelas">
                @foreach ($classrooms as $c)
                    <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:radio.group wire:model.live="type" variant="segmented" aria-label="Jenis periode">
                <flux:radio value="weekly">Mingguan</flux:radio>
                <flux:radio value="monthly">Bulanan</flux:radio>
            </flux:radio.group>
            <div class="flex items-center justify-between gap-1 rounded-lg border border-woka-100 px-1 dark:border-hutan-800">
                <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Periode sebelumnya" />
                <div class="min-w-0 text-center leading-tight">
                    <p class="truncate text-sm font-semibold">{{ $periodLabel }}</p>
                    @unless ($isCurrentPeriod)
                        <button type="button" wire:click="thisPeriod" class="text-xs font-semibold text-woka hover:underline dark:text-woka-light">Ke periode ini</button>
                    @endunless
                </div>
                <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Periode berikutnya" />
            </div>
        </div>
    </x-card>

    @if (! $current)
        <x-empty-state icon="building-library" title="Belum ada kelas yang Anda ajar" />
    @else
        {{-- Langkah 2: aspek yang dinilai --}}
        <x-card class="mb-3">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">2 · Aspek yang dinilai</p>
                    <p class="mt-0.5 text-xs text-hutan/60 dark:text-santan/60">{{ $customized ? 'Dipilih pengajar untuk kelas ini' : 'Memakai aspek bawaan' }} · berlaku untuk semua periode kelas ini.</p>
                </div>
                <flux:button size="sm" icon="adjustments-horizontal" wire:click="openAspects" class="shrink-0">Atur aspek</flux:button>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @forelse ($aspects as $aspect)
                    <span wire:key="chip-{{ $aspect->id }}" class="inline-flex items-center gap-1 rounded-full bg-woka-50 px-2.5 py-1 text-sm font-semibold text-woka-800 dark:bg-hutan-800 dark:text-santan">
                        {{ $aspect->name }}
                        @if ($aspect->source_aspect)
                            <flux:icon.sparkles variant="micro" class="text-woka dark:text-woka-light" aria-label="ada saran nilai otomatis" />
                        @endif
                    </span>
                @empty
                    <p class="text-sm text-rica">Belum ada aspek. Tekan <strong>Atur aspek</strong> untuk memilih.</p>
                @endforelse
            </div>
        </x-card>

        {{-- Langkah 3: isi nilai --}}
        <x-card class="mb-3">
            @php
                $scoredCount = $counts['draft'] + $counts['published'];
                $progress = $total > 0 ? round($scoredCount / $total * 100) : 0;
            @endphp
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">3 · Isi nilai</p>
                    <p class="mt-0.5 text-sm"><strong>{{ $scoredCount }}/{{ $total }}</strong> peserta sudah dinilai · <strong>{{ $counts['published'] }}</strong> terbit</p>
                </div>
                <flux:button size="sm" icon="sparkles" wire:click="fillSuggestions" :disabled="$aspects->isEmpty()">Isi saran nilai</flux:button>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Kemajuan penilaian">
                <div class="h-full rounded-full bg-woka" style="width: {{ $progress }}%"></div>
            </div>

            <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,16rem)_1fr] sm:items-center">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau NIS…" clearable aria-label="Cari peserta" />
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                    @foreach (self::STATES as $value => $label)
                        <button type="button" wire:click="$set('status', '{{ $value }}')" wire:key="st-{{ $value ?: 'all' }}"
                                @class(['shrink-0 rounded-full border px-3 py-1.5 text-sm font-semibold transition',
                                    'border-woka bg-woka text-white' => $status === $value,
                                    'border-woka-100 bg-white hover:border-woka dark:border-hutan-800 dark:bg-hutan-900' => $status !== $value])>
                            {{ $label }} <span @class(['ms-0.5 text-xs', 'text-white/80' => $status === $value, 'text-hutan/50 dark:text-santan/50' => $status !== $value])>{{ $counts[$value] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </x-card>

        @if ($total === 0)
            <x-empty-state icon="user-group" title="Belum ada peserta di kelas ini" />
        @elseif ($participants->isEmpty())
            <x-empty-state icon="funnel" title="Tidak ada peserta yang cocok dengan filter">
                <flux:button size="sm" class="mt-3" wire:click="resetFilters">Tampilkan semua peserta</flux:button>
            </x-empty-state>
        @else
            <div class="space-y-3" x-data="{
                    thresholds: @js($thresholds),
                    aspects: @js($aspects->pluck('id')->map(fn ($id) => (string) $id)->values()),
                    num(v) {
                        if (v === '' || v === null || v === undefined) return null;
                        const n = Number(String(v).replace(',', '.'));
                        return Number.isNaN(n) ? NaN : n;
                    },
                    bad(v) { const n = this.num(v); return n !== null && (Number.isNaN(n) || n < 0 || n > 100); },
                    avg(uid) {
                        const scores = ($wire.rows[uid] || {}).scores || {};
                        const vals = this.aspects.map(a => this.num(scores[a])).filter(n => n !== null && !Number.isNaN(n));
                        return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
                    },
                    grade(v) {
                        if (v === null) return '–';
                        return v >= this.thresholds.A ? 'A' : v >= this.thresholds.B ? 'B' : v >= this.thresholds.C ? 'C' : 'D';
                    },
                 }">
                @foreach ($participants as $participant)
                    @php
                        $userId = $participant->id;
                        $state = $states[$userId];
                        $assessment = $assessments->get($userId);
                    @endphp
                    <x-card wire:key="as-{{ $userId }}-{{ implode('.', $aspectIds) }}" x-data="{ open: {{ filled($rows[$userId]['feedback'] ?? '') ? 'true' : 'false' }} }"
                            :class="$errors->has('rows.'.$userId) ? 'ring-2 ring-rica' : ''">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $participant->name }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <flux:badge size="sm" :color="self::STATE_COLORS[$state]">{{ self::STATES[$state] }}</flux:badge>
                                    @if ($participant->username)
                                        <span class="text-xs text-hutan/50 dark:text-santan/50">NIS {{ $participant->username }}</span>
                                    @endif
                                    @if ($assessment?->isPublished())
                                        <button type="button" class="text-xs font-semibold text-woka hover:underline dark:text-woka-light" wire:click="unpublish({{ $userId }})">Kembalikan ke draf</button>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 text-end">
                                <p class="font-display text-2xl font-extrabold leading-none" x-text="avg({{ $userId }}) === null ? '–' : avg({{ $userId }}).toLocaleString('id-ID', {maximumFractionDigits: 1})"></p>
                                <p class="text-xs text-hutan/60 dark:text-santan/60">Predikat <span class="font-bold" x-text="grade(avg({{ $userId }}))"></span></p>
                            </div>
                        </div>

                        @error("rows.$userId")
                            <p class="mt-2 text-sm font-semibold text-rica">{{ $message }}</p>
                        @enderror

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-[repeat(auto-fill,minmax(7.5rem,1fr))]">
                            @foreach ($aspects as $aspect)
                                @php
                                    $suggest = $suggestions[$userId][$aspect->id] ?? null;
                                @endphp
                                <label class="block min-w-0" wire:key="in-{{ $userId }}-{{ $aspect->id }}">
                                    <span class="block truncate text-xs font-semibold text-hutan/70 dark:text-santan/70" title="{{ $aspect->name }}">{{ $aspect->name }}</span>
                                    <input type="text" inputmode="decimal" enterkeyhint="next" autocomplete="off"
                                           wire:model="rows.{{ $userId }}.scores.{{ $aspect->id }}"
                                           placeholder="{{ $suggest !== null ? 'saran '.round($suggest) : '0–100' }}"
                                           x-bind:class="bad($wire.rows[{{ $userId }}]?.scores?.[{{ $aspect->id }}]) ? 'border-rica ring-2 ring-rica/40' : 'border-woka-100 dark:border-hutan-800'"
                                           class="mt-1 h-11 w-full rounded-lg border bg-white px-2 text-center font-semibold placeholder:text-xs placeholder:font-normal focus:outline-none focus:ring-2 focus:ring-woka dark:bg-hutan-950">
                                </label>
                            @endforeach
                        </div>

                        <button type="button" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-woka hover:underline dark:text-woka-light" x-on:click="open = !open">
                            <flux:icon.chat-bubble-left-ellipsis variant="mini" />
                            <span x-text="open ? 'Tutup masukan' : '{{ filled($rows[$userId]['feedback'] ?? '') ? 'Lihat masukan' : 'Tulis masukan' }}'"></span>
                        </button>
                        <div x-show="open" x-collapse class="mt-2 space-y-2">
                            <flux:textarea wire:model="rows.{{ $userId }}.feedback" rows="3" placeholder="Masukan untuk peserta…" />
                            <flux:switch wire:model="rows.{{ $userId }}.visible_to_guardian" label="Boleh dilihat wali" />
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif

        {{-- Langkah 4: simpan & terbitkan (selalu terlihat) --}}
        @if ($total > 0 && $aspects->isNotEmpty())
            <div class="sticky bottom-0 z-20 -mx-4 mt-4 border-t border-woka-100 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border dark:border-hutan-800 dark:bg-hutan-900/95">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="hidden text-xs text-hutan/60 sm:block dark:text-santan/60">4 · Simpan draf dulu bila belum selesai. Peserta & wali baru melihat nilai setelah <strong>Terbitkan</strong>.</p>
                    <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                        <flux:button icon="document-check" wire:click="saveDraft">Simpan draf</flux:button>
                        <x-confirm-button action="publish" label="Terbitkan" icon="paper-airplane" variant="primary" size="base"
                                          title="Terbitkan penilaian {{ $periodLabel }}?" message="Semua peserta yang sudah berisi nilai akan diterbitkan. Peserta (dan wali bila diizinkan) dapat melihat nilai serta mendapat notifikasi." confirmLabel="Terbitkan" />
                    </div>
                </div>
            </div>
        @endif
    @endif

    <flux:modal wire:model.self="showAspects" class="w-full max-w-lg">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Aspek yang dinilai</flux:heading>
                <flux:text class="mt-1">Centang aspek untuk {{ $current?->label() }}. Berlaku untuk semua periode; nilai yang sudah tersimpan tidak hilang.</flux:text>
            </div>

            <div class="max-h-[50vh] space-y-2 overflow-y-auto pe-1">
                @foreach ($available as $aspect)
                    <label wire:key="opt-{{ $aspect->id }}" class="flex cursor-pointer items-start gap-3 rounded-xl border border-woka-100 p-3 has-[:checked]:border-woka has-[:checked]:bg-woka-50 dark:border-hutan-800 dark:has-[:checked]:bg-hutan-800">
                        <input type="checkbox" value="{{ $aspect->id }}" wire:model="chosenAspects" class="mt-0.5 size-5 shrink-0 accent-woka">
                        <span class="min-w-0">
                            <span class="font-semibold">{{ $aspect->name }}</span>
                            <span class="ms-1 text-xs text-hutan/50 dark:text-santan/50">{{ $aspect->language ? 'Khusus '.$aspect->language->name : 'Semua bahasa' }}{{ $aspect->is_default ? ' · bawaan' : '' }}</span>
                            @if ($aspect->description)
                                <span class="block text-xs text-hutan/70 dark:text-santan/70">{{ $aspect->description }}</span>
                            @endif
                            <span class="block text-xs text-woka dark:text-woka-light">{{ $aspect->source_aspect ? 'Saran nilai dari komponen '.$aspect->source_aspect->label() : 'Diisi manual' }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('chosenAspects')
                <p class="text-sm font-semibold text-rica">{{ $message }}</p>
            @enderror

            <p class="text-xs text-hutan/60 dark:text-santan/60">
                Aspek yang dibutuhkan belum ada?
                @can('assessment-aspect.manage')
                    <a href="{{ route('master.assessment-aspects') }}" wire:navigate class="font-semibold text-woka hover:underline dark:text-woka-light">Tambah di Master Data → Aspek Penilaian</a>.
                @else
                    Minta admin program menambahkannya di Master Data → Aspek Penilaian.
                @endcan
            </p>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:button variant="ghost" size="sm" wire:click="resetAspects">Pakai aspek bawaan</flux:button>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button variant="primary" wire:click="saveAspects">Simpan aspek</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
