<?php

use App\Actions\Quizzes\ManageQuiz;
use App\Enums\AttemptStatus;
use App\Enums\QuizStatus;
use App\Enums\QuizType;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Services\QuizAnalytics;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Detail quiz')] class extends Component {
    public Quiz $quiz;

    #[Url]
    public string $tab = 'soal';

    public ?int $bank = null;

    /** @var list<int> */
    public array $selected = [];

    public string $section = '';

    /** @var array<int, array{points: mixed, section: mixed}> */
    public array $pivots = [];

    public function mount(Quiz $quiz): void
    {
        $this->authorize('manage', $quiz);
        $this->quiz = $quiz->load(['classroom.language', 'session']);
        $this->bank = QuestionBank::query()->where('language_id', $quiz->classroom->language_id)->value('id');
        $this->loadPivots();
    }

    protected function loadPivots(): void
    {
        $this->pivots = $this->quiz->questions()->get()->mapWithKeys(fn ($q) => [$q->id => ['points' => $q->pivot->points, 'section' => $q->pivot->section ?? '']])->all();
    }

    protected function run(callable $action, string $message): void
    {
        $this->authorize('manage', $this->quiz);

        try {
            $action();
            Flux::toast(variant: 'success', text: $message);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->quiz->refresh();
        $this->loadPivots();
    }

    public function addSelected(ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->addQuestions($this->quiz, $this->selected, $this->section), count($this->selected).' soal ditambahkan.');
        $this->selected = [];
    }

    public function remove(int $questionId, ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->removeQuestion($this->quiz, $questionId), 'Soal dikeluarkan dari quiz.');
    }

    public function move(int $questionId, int $direction, ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->move($this->quiz, $questionId, $direction), 'Urutan diperbarui.');
    }

    public function savePivot(int $questionId, ManageQuiz $manage): void
    {
        $pivot = $this->pivots[$questionId] ?? [];
        $points = is_numeric($pivot['points'] ?? null) ? (float) $pivot['points'] : null;
        $this->run(fn () => $manage->updatePivot($this->quiz, $questionId, $points, $pivot['section'] ?? null), 'Tersimpan.');
    }

    public function publish(ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->publish($this->quiz), 'Quiz diterbitkan. Peserta mendapat notifikasi.');
    }

    public function close(ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->close($this->quiz), 'Quiz ditutup.');
    }

    public function release(ManageQuiz $manage): void
    {
        $this->run(fn () => $manage->releaseResults($this->quiz), 'Hasil dirilis ke peserta.');
    }

    public function with(): array
    {
        $questions = $this->quiz->questions()->with('options')->get();
        $data = [
            'questions' => $questions,
            'totalPoints' => $questions->sum(fn ($q) => $this->quiz->pointsFor($q)),
            'locked' => $this->quiz->attempts()->exists(),
            'isCjk' => $this->quiz->classroom->language->isCjk(),
            'tabs' => ['soal' => 'Soal', 'hasil' => 'Hasil', 'analisis' => 'Analisis butir'] + (in_array($this->quiz->type, [QuizType::Pretest, QuizType::Posttest], true) ? ['ngain' => 'N-Gain'] : []),
        ];

        if ($this->tab === 'soal') {
            $data['banks'] = QuestionBank::query()->where('language_id', $this->quiz->classroom->language_id)->withCount('questions')->orderBy('name')->get();
            $data['bankQuestions'] = $this->bank ? QuestionBank::find($this->bank)?->questions()->whereNotIn('questions.id', $questions->pluck('id'))->get() ?? collect() : collect();
        }

        if ($this->tab === 'hasil') {
            $data['attempts'] = $this->quiz->attempts()->with('user.profile')->orderBy('user_id')->orderBy('id')->get();
            $data['notAttempted'] = $this->quiz->classroom->participants()->whereNotIn('users.id', $data['attempts']->pluck('user_id'))->orderBy('name')->get();
        }

        if ($this->tab === 'analisis') {
            $data['items'] = app(QuizAnalytics::class)->itemAnalysis($this->quiz);
        }

        if ($this->tab === 'ngain') {
            $data['ngain'] = app(QuizAnalytics::class)->nGain($this->quiz->classroom);
        }

        return $data;
    }
}; ?>

<div>
    @if ($isCjk)<x-cjk-fonts />@endif

    <x-page-header :title="$quiz->title" :back="route('teach.quizzes.index')"
                   :subtitle="$quiz->classroom->label().' · '.$quiz->duration_minutes.' menit · '.$questions->count().' soal · '.angka($totalPoints, 1).' poin'">
        <x-slot:actions>
            <x-status-badge :status="$quiz->type" size="lg" />
            <x-status-badge :status="$quiz->status" size="lg" />
            @if ($quiz->status === QuizStatus::Draft)
                <flux:button variant="primary" icon="paper-airplane" wire:click="publish">Terbitkan</flux:button>
            @elseif ($quiz->status === QuizStatus::Published)
                <x-confirm-button action="close" label="Tutup quiz" icon="lock-closed" variant="danger" size="base" title="Tutup quiz sekarang?" message="Peserta tidak bisa memulai attempt baru." confirmLabel="Tutup" />
            @endif
            @if ($quiz->result_visibility === \App\Enums\ResultVisibility::Manual && ! $quiz->results_released_at)
                <flux:button icon="eye" wire:click="release">Rilis hasil</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['min-h-11 flex-1 whitespace-nowrap rounded-lg px-3 text-sm font-semibold', 'bg-woka text-white' => $tab === $key, 'text-hutan/70 dark:text-santan/70 hover:bg-woka-50 dark:text-santan/70' => $tab !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'soal')
        @if ($locked)
            <flux:callout icon="lock-closed" class="mb-4" text="Soal terkunci karena quiz sudah dikerjakan peserta." />
        @endif
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <div class="space-y-2">
                @forelse ($questions as $i => $question)
                    <x-card wire:key="qq-{{ $question->id }}">
                        <div class="flex items-start gap-3">
                            <span class="font-display text-lg font-bold text-hutan/50 dark:text-santan/50">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex flex-wrap gap-1"><x-status-badge :status="$question->type" /></div>
                                <x-reading-text :text="\Illuminate\Support\Str::limit($question->body, 160)" :reading="$question->reading" :display="$quiz->reading_display" as="p" class="font-semibold" />
                                @unless ($locked)
                                    <div class="mt-2 flex flex-wrap items-end gap-2">
                                        <flux:input size="sm" wire:model="pivots.{{ $question->id }}.points" :placeholder="'poin '.angka($question->points, 1)" class="w-24" />
                                        @if ($quiz->type === QuizType::Tryout)
                                            <flux:input size="sm" wire:model="pivots.{{ $question->id }}.section" placeholder="Bagian (听力/阅读)" class="w-40" />
                                        @endif
                                        <flux:button size="sm" wire:click="savePivot({{ $question->id }})">Simpan</flux:button>
                                    </div>
                                @else
                                    <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">{{ angka($quiz->pointsFor($question), 1) }} poin {{ $question->pivot->section ? '· '.$question->pivot->section : '' }}</p>
                                @endunless
                            </div>
                            @unless ($locked)
                                <div class="flex shrink-0 flex-col">
                                    <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="move({{ $question->id }}, -1)" aria-label="Naik" />
                                    <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="move({{ $question->id }}, 1)" aria-label="Turun" />
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="remove({{ $question->id }})" aria-label="Keluarkan" />
                                </div>
                            @endunless
                        </div>
                    </x-card>
                @empty
                    <x-empty-state icon="rectangle-stack" title="Belum ada soal" description="Pilih soal dari bank di panel samping." />
                @endforelse
            </div>

            @unless ($locked)
                <x-card title="Tambah dari bank soal">
                    <flux:select wire:model.live="bank" class="mb-3">
                        @foreach ($banks as $b)
                            <flux:select.option :value="$b->id">{{ $b->name }} ({{ $b->questions_count }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($quiz->type === QuizType::Tryout)
                        <flux:input wire:model="section" label="Bagian untuk soal terpilih" placeholder="听力 Listening" class="mb-3" />
                    @endif
                    <div class="max-h-[60vh] space-y-1 overflow-y-auto">
                        @forelse ($bankQuestions as $bq)
                            <label class="flex items-start gap-2 rounded-lg p-2 hover:bg-woka-50 dark:hover:bg-hutan-800" wire:key="bq-{{ $bq->id }}">
                                <input type="checkbox" wire:model="selected" value="{{ $bq->id }}" class="mt-1 size-5 shrink-0 accent-woka">
                                <span class="min-w-0 text-sm">
                                    <x-status-badge :status="$bq->type" />
                                    <span class="mt-0.5 block">{{ \Illuminate\Support\Str::limit($bq->body, 120) }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-hutan/60 dark:text-santan/60">Tidak ada soal tersisa di bank ini. <a href="{{ route('teach.questions.index') }}" wire:navigate class="font-semibold text-woka">Buka bank soal</a></p>
                        @endforelse
                    </div>
                    @if ($bankQuestions->isNotEmpty())
                        <flux:button class="mt-3 w-full" variant="primary" icon="plus" wire:click="addSelected">Tambahkan terpilih</flux:button>
                    @endif
                </x-card>
            @endunless
        </div>
    @elseif ($tab === 'hasil')
        <div class="table-scroll">
            <table>
                <thead><tr><th>Peserta</th><th>Mulai</th><th>Status</th><th>Skor</th><th>%</th>@if ($quiz->type === QuizType::Tryout)<th>Skala</th><th>Target</th>@endif<th>Pindah tab</th></tr></thead>
                <tbody>
                    @foreach ($attempts as $attempt)
                        <tr wire:key="at-{{ $attempt->id }}">
                            <td class="font-semibold">{{ $attempt->user->name }}</td>
                            <td>{{ tanggal_jam($attempt->started_at) }}</td>
                            <td>
                                <x-status-badge :status="$attempt->status" />
                                @if ($attempt->auto_submitted)<flux:badge size="sm" color="zinc">otomatis</flux:badge>@endif
                            </td>
                            <td>{{ $attempt->score !== null ? angka($attempt->score, 1).'/'.angka($attempt->max_score, 1) : '–' }}</td>
                            <td @class(['font-bold', 'text-woka' => $attempt->passed(), 'text-rica' => $attempt->percentage !== null && ! $attempt->passed()])>{{ $attempt->percentage !== null ? angka($attempt->percentage, 1) : '–' }}</td>
                            @if ($quiz->type === QuizType::Tryout)
                                <td>{{ $attempt->scaledScore() !== null ? angka($attempt->scaledScore()).'/'.$quiz->score_scale : '–' }}</td>
                                <td>{{ $attempt->user->profile?->target_score ?? '–' }}</td>
                            @endif
                            <td>
                                @if ($attempt->tab_switch_count)
                                    <flux:badge size="sm" :color="$attempt->tab_switch_count >= 3 ? 'red' : 'amber'">{{ $attempt->tab_switch_count }}×</flux:badge>
                                @else – @endif
                            </td>
                        </tr>
                    @endforeach
                    @foreach ($notAttempted as $user)
                        <tr wire:key="na-{{ $user->id }}" class="text-hutan/50 dark:text-santan/50">
                            <td>{{ $user->name }}</td><td colspan="{{ $quiz->type === QuizType::Tryout ? 7 : 5 }}">Belum mengerjakan</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($attempts->where('status', AttemptStatus::NeedsGrading)->isNotEmpty())
            <flux:button class="mt-3" variant="primary" icon="inbox-stack" :href="route('teach.grading', ['quiz' => $quiz->id])" wire:navigate>Nilai jawaban esai/berbicara</flux:button>
        @endif
    @elseif ($tab === 'analisis')
        @if ($items->isEmpty())
            <x-empty-state icon="chart-bar" title="Belum ada attempt yang dinilai" />
        @else
            <div class="table-scroll">
                <table>
                    <thead><tr><th>#</th><th>Soal</th><th>Kesukaran (p)</th><th>Daya pembeda (D)</th></tr></thead>
                    <tbody>
                        @foreach ($items as $i => $item)
                            <tr wire:key="ia-{{ $i }}">
                                <td>{{ $i + 1 }}</td>
                                <td class="max-w-md">{{ \Illuminate\Support\Str::limit($item['question']->body, 80) }}</td>
                                <td>{{ angka($item['difficulty'], 2) }} · <span class="text-xs">{{ $item['difficulty_label'] }}</span></td>
                                <td @class(['text-rica' => $item['discrimination'] < 0.2])>{{ angka($item['discrimination'], 2) }} · <span class="text-xs">{{ $item['discrimination_label'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Kesukaran: proporsi skor (mudah &gt; 0,7; sukar &lt; 0,3). Daya pembeda: selisih proporsi kelompok atas dan bawah 27%.</p>
        @endif
    @elseif ($tab === 'ngain')
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat label="Rata-rata pretest" :value="$ngain['pre_avg'] !== null ? angka($ngain['pre_avg'], 1) : '–'" icon="arrow-down-circle" tone="kunyit" />
            <x-stat label="Rata-rata posttest" :value="$ngain['post_avg'] !== null ? angka($ngain['post_avg'], 1) : '–'" icon="arrow-up-circle" />
            <x-stat label="N-Gain kelas" :value="$ngain['average'] !== null ? angka($ngain['average'], 2) : '–'" icon="arrow-trending-up" />
            <x-stat label="Kategori" :value="\App\Services\QuizAnalytics::category($ngain['average']) ?? '–'" icon="flag" tone="hutan" />
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Peserta</th><th>Pretest</th><th>Posttest</th><th>N-Gain</th><th>Kategori</th></tr></thead>
                <tbody>
                    @foreach ($ngain['rows'] as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['user']->name }}</td>
                            <td>{{ $row['pre'] !== null ? angka($row['pre'], 1) : '–' }}</td>
                            <td>{{ $row['post'] !== null ? angka($row['post'], 1) : '–' }}</td>
                            <td class="font-bold">{{ $row['gain'] !== null ? angka($row['gain'], 2) : '–' }}</td>
                            <td>
                                @if ($row['category'])
                                    <flux:badge size="sm" :color="['Tinggi' => 'green', 'Sedang' => 'amber', 'Rendah' => 'red'][$row['category']]">{{ $row['category'] }}</flux:badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">N-Gain = (post − pre) / (100 − pre). Tinggi ≥ 0,7; sedang 0,3–0,7; rendah &lt; 0,3.</p>
    @endif
</div>
