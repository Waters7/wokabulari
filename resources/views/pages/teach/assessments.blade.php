<?php

use App\Actions\Assessments\SaveAssessment;
use App\Enums\LanguageAspect;
use App\Enums\PeriodType;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\User;
use App\Services\AssessmentSuggester;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Penilaian periodik')] class extends Component {
    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $type = 'weekly';

    #[Url]
    public string $period = '';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, array<string, float|null>> */
    public array $suggestions = [];

    public function mount(): void
    {
        $this->classroom ??= $this->classrooms()->first()?->id;
        $this->period = $this->period ?: now()->toDateString();
        $this->load();
    }

    protected function classrooms()
    {
        $user = auth()->user();

        return Classroom::query()->active()->with('language')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
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

    protected function load(): void
    {
        $this->rows = [];
        $this->suggestions = [];
        $classroom = $this->currentClassroom();

        if (! $classroom) {
            return;
        }

        $this->authorize('manage', [Assessment::class, $classroom]);
        [$start, $end] = $this->range();

        $existing = Assessment::query()->where('classroom_id', $classroom->id)->where('period_type', $this->periodType())
            ->whereDate('period_start', $start)->get()->keyBy('user_id');

        $suggester = app(AssessmentSuggester::class);

        foreach ($classroom->participants()->orderBy('name')->get() as $participant) {
            $assessment = $existing->get($participant->id);
            $row = ['feedback' => $assessment?->feedback ?? '', 'visible_to_guardian' => $assessment?->visible_to_guardian ?? true];
            foreach (LanguageAspect::periodic() as $aspect) {
                $value = $assessment?->{$aspect->value};
                $row[$aspect->value] = $value === null ? '' : (string) $value;
            }
            $this->rows[$participant->id] = $row;
            $this->suggestions[$participant->id] = $suggester->suggest($classroom, $participant->id, $start, $end);
        }
    }

    /** Isi kolom kosong dengan saran dari komponen sesi, quiz, dan tugas pada periode ini. */
    public function fillSuggestions(): void
    {
        foreach ($this->rows as $userId => $row) {
            foreach (LanguageAspect::periodic() as $aspect) {
                if (($row[$aspect->value] ?? '') === '' && ($this->suggestions[$userId][$aspect->value] ?? null) !== null) {
                    $this->rows[$userId][$aspect->value] = (string) round($this->suggestions[$userId][$aspect->value]);
                }
            }
        }

        Flux::toast(text: 'Kolom kosong diisi dengan saran nilai. Silakan periksa sebelum menyimpan.');
    }

    protected function saveAll(SaveAssessment $save): array
    {
        $classroom = $this->currentClassroom();
        $this->authorize('manage', [Assessment::class, $classroom]);
        $this->resetErrorBag();

        $saved = [];
        foreach ($this->rows as $userId => $row) {
            try {
                $saved[] = $save->save($classroom, User::findOrFail($userId), auth()->user(), $this->periodType(), CarbonImmutable::parse($this->period), $row);
            } catch (\InvalidArgumentException $e) {
                $this->addError("rows.{$userId}", $e->getMessage());
            }
        }

        return $saved;
    }

    public function saveDraft(SaveAssessment $save): void
    {
        $saved = $this->saveAll($save);

        if ($this->getErrorBag()->isEmpty()) {
            Flux::toast(variant: 'success', text: count($saved).' penilaian tersimpan sebagai draf.');
        }
    }

    public function publish(SaveAssessment $save): void
    {
        $saved = $this->saveAll($save);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $count = $save->publish($saved);
        Flux::toast(variant: 'success', text: "{$count} penilaian diterbitkan. Peserta mendapat notifikasi.");
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

    public function with(): array
    {
        $classroom = $this->currentClassroom();
        [$start] = $this->range();

        return [
            'classrooms' => $this->classrooms(),
            'participants' => $classroom ? $classroom->participants()->orderBy('name')->get()->keyBy('id') : collect(),
            'assessments' => $classroom ? Assessment::query()->where('classroom_id', $classroom->id)->where('period_type', $this->periodType())->whereDate('period_start', $start)->get()->keyBy('user_id') : collect(),
            'aspects' => LanguageAspect::periodic(),
            'periodLabel' => $this->periodType()->periodLabel($start),
            'thresholds' => ['A' => (float) setting('grade.threshold_a'), 'B' => (float) setting('grade.threshold_b'), 'C' => (float) setting('grade.threshold_c')],
        ];
    }
}; ?>

<div>
    <x-page-header title="Penilaian periodik" subtitle="Nilai mingguan/bulanan per aspek. Peserta dan wali hanya melihat penilaian yang sudah terbit." />

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:select wire:model.live="classroom">
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:radio.group wire:model.live="type" variant="segmented">
            <flux:radio value="weekly">Mingguan</flux:radio>
            <flux:radio value="monthly">Bulanan</flux:radio>
        </flux:radio.group>
        <div class="flex items-center justify-between gap-1">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Periode sebelumnya" />
            <span class="text-center text-sm font-semibold">{{ $periodLabel }}</span>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Periode berikutnya" />
        </div>
    </div>

    @if ($participants->isEmpty())
        <x-empty-state icon="document-chart-bar" title="Tidak ada peserta" />
    @else
        <div class="mb-4 flex flex-wrap gap-2">
            <flux:button icon="sparkles" wire:click="fillSuggestions">Isi saran nilai</flux:button>
            <flux:button icon="document-check" wire:click="saveDraft">Simpan draf</flux:button>
            <x-confirm-button action="publish" label="Terbitkan" icon="paper-airplane" variant="primary" size="base"
                              title="Terbitkan penilaian?" message="Peserta (dan wali bila diizinkan) dapat melihat nilai serta mendapat notifikasi." confirmLabel="Terbitkan" />
        </div>

        <div class="space-y-3" x-data="{
                thresholds: @js($thresholds),
                aspects: @js(collect($aspects)->map->value),
                avg(uid) {
                    const row = $wire.rows[uid] || {};
                    const vals = this.aspects.map(a => row[a]).filter(v => v !== '' && v !== null && !isNaN(parseFloat(v))).map(v => parseFloat(String(v).replace(',', '.')));
                    return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
                },
                grade(v) {
                    if (v === null) return '–';
                    return v >= this.thresholds.A ? 'A' : v >= this.thresholds.B ? 'B' : v >= this.thresholds.C ? 'C' : 'D';
                },
             }">
            @foreach ($participants as $userId => $participant)
                @php($assessment = $assessments->get($userId))
                <x-card wire:key="as-{{ $userId }}" x-data="{ open: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $participant->name }}</p>
                            <div class="flex items-center gap-1">
                                <x-status-badge :status="$assessment?->status ?? \App\Enums\PublishStatus::Draft" />
                                @if ($assessment?->isPublished())
                                    <button type="button" class="text-xs font-semibold text-woka hover:underline" wire:click="unpublish({{ $userId }})">Kembalikan ke draf</button>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="font-display text-2xl font-extrabold leading-none" x-text="avg({{ $userId }}) === null ? '–' : avg({{ $userId }}).toLocaleString('id-ID', {maximumFractionDigits: 1})"></p>
                            <p class="text-xs text-hutan/60">Predikat <span class="font-bold" x-text="grade(avg({{ $userId }}))"></span></p>
                        </div>
                    </div>

                    @error("rows.$userId")
                        <p class="mt-2 text-sm text-rica">{{ $message }}</p>
                    @enderror

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                        @foreach ($aspects as $aspect)
                            @php($suggest = $suggestions[$userId][$aspect->value] ?? null)
                            <label class="block">
                                <span class="text-xs font-semibold text-hutan/70 dark:text-santan/70">{{ $aspect->label() }}</span>
                                <input type="text" inputmode="decimal" wire:model="rows.{{ $userId }}.{{ $aspect->value }}"
                                       placeholder="{{ $suggest !== null ? 'saran '.round($suggest) : '0–100' }}"
                                       class="mt-1 h-11 w-full rounded-lg border border-woka-100 bg-white px-2 text-center font-semibold placeholder:text-xs placeholder:font-normal focus:outline-none focus:ring-2 focus:ring-woka dark:border-hutan-800 dark:bg-hutan-950">
                            </label>
                        @endforeach
                    </div>

                    <button type="button" class="mt-3 text-sm font-semibold text-woka hover:underline" x-on:click="open = !open" x-text="open ? 'Tutup masukan' : 'Tulis masukan'"></button>
                    <div x-show="open" x-collapse class="mt-2 space-y-2">
                        <flux:textarea wire:model="rows.{{ $userId }}.feedback" rows="3" placeholder="Masukan untuk peserta…" />
                        <flux:switch wire:model="rows.{{ $userId }}.visible_to_guardian" label="Boleh dilihat wali" />
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
