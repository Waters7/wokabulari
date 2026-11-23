<?php

use App\Enums\QuestionType;
use App\Models\Quiz;
use App\Services\GradeService;
use App\Services\Reports\QuizPaper;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Cetak quiz')] class extends Component {
    public Quiz $quiz;

    /** soal | hasil | semua */
    #[Url(as: 'bagian')]
    public string $part = 'soal';

    /** Tampilkan kunci jawaban (lembar pengajar) atau tidak (lembar peserta). */
    #[Url(as: 'kunci')]
    public bool $withKey = false;

    public function mount(Quiz $quiz): void
    {
        $this->authorize('manage', $quiz);
        $this->quiz = $quiz->load(['classroom.language', 'classroom.batch', 'teacher']);
        $this->part = in_array($this->part, ['soal', 'hasil', 'semua'], true) ? $this->part : 'soal';
    }

    public function with(QuizPaper $paper, GradeService $grades): array
    {
        $questions = $paper->questions($this->quiz);

        return [
            'paper' => $paper,
            'grades' => $grades,
            'questions' => $questions,
            'totalPoints' => $questions->sum('points'),
            'results' => $this->part === 'soal' ? null : $paper->results($this->quiz),
            'isCjk' => $this->quiz->classroom->language->isCjk(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl print:max-w-none">
    @if ($isCjk)<x-cjk-fonts />@endif

    <div class="mb-4 space-y-3 print:hidden">
        <a href="{{ route('teach.quizzes.show', $quiz) }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-woka hover:underline dark:text-woka-light">
            <flux:icon.arrow-left class="size-4" /> Kembali ke quiz
        </a>
        <div class="flex flex-wrap items-end gap-3">
            <flux:radio.group wire:model.live="part" variant="segmented" label="Yang dicetak">
                <flux:radio value="soal">Soal</flux:radio>
                <flux:radio value="hasil">Hasil</flux:radio>
                <flux:radio value="semua">Soal + hasil</flux:radio>
            </flux:radio.group>
            @if ($part !== 'hasil')
                <flux:switch wire:model.live="withKey" label="Tampilkan kunci jawaban" />
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="primary" icon="printer" x-on:click="window.print()">Cetak</flux:button>
            <flux:button icon="arrow-down-tray" :href="route('teach.quizzes.excel', $quiz)">Unduh Excel (soal + hasil)</flux:button>
        </div>
        @if ($part !== 'hasil')
            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $withKey ? 'Lembar pengajar: kunci jawaban ikut tercetak. Jangan dibagikan ke peserta.' : 'Lembar peserta: tanpa kunci jawaban, dengan tempat menulis nama dan jawaban.' }}</p>
        @endif
    </div>

    <article class="rounded-2xl bg-white p-5 text-hutan ring-1 ring-woka-100 sm:p-8 dark:bg-hutan-900 dark:text-santan dark:ring-hutan-800 print:rounded-none print:p-0 print:ring-0" data-test="quiz-paper">
        <header class="border-b-2 border-woka pb-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-hutan/60 dark:text-santan/60">{{ setting('program.name') }} · {{ setting('program.organizer') }}</p>
            <h1 class="mt-1 font-display text-2xl font-extrabold">{{ $quiz->title }}</h1>
            <p class="mt-1 text-sm">{{ $quiz->classroom->label() }} · {{ $quiz->type->label() }} · {{ $quiz->duration_minutes }} menit · {{ $questions->count() }} soal · {{ poin($totalPoints) }} poin
                @if ($quiz->opens_at) · {{ tanggal($quiz->opens_at, 'j F Y') }}@endif</p>
        </header>

        @if ($part !== 'hasil')
            @unless ($withKey)
                <div class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2 print:grid-cols-2">
                    @foreach (['Nama', 'NIS', 'Kelas', 'Tanggal'] as $field)
                        <p class="flex items-end gap-2">{{ $field }}<span class="mb-1 flex-1 border-b border-dotted border-hutan/50"></span></p>
                    @endforeach
                </div>
            @endunless

            <ol class="mt-5 space-y-5" data-test="paper-questions">
                @foreach ($questions as $item)
                    @php
                        $question = $item['question'];
                    @endphp
                    <li class="break-inside-avoid" wire:key="pq-{{ $question->id }}">
                        <div class="flex gap-3">
                            <span class="w-6 shrink-0 text-right font-bold">{{ $item['no'] }}.</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <x-reading-text :text="$question->body" :reading="$question->reading" :display="$quiz->reading_display" as="p" class="whitespace-pre-line font-semibold" />
                                    <span class="shrink-0 text-xs text-hutan/60 dark:text-santan/60">{{ poin($item['points']) }} poin</span>
                                </div>
                                @if ($question->pivot->section)<p class="text-xs text-hutan/60 dark:text-santan/60">{{ $question->pivot->section }}</p>@endif

                                @if ($question->hasImage())
                                    <img src="{{ $question->mediaUrl() }}" alt="Gambar soal {{ $item['no'] }}" class="mt-2 max-h-56 rounded-lg object-contain">
                                @elseif ($question->media_path)
                                    <p class="mt-1 text-xs italic text-hutan/60 dark:text-santan/60">(Soal dengan audio — diputar dari aplikasi)</p>
                                @endif

                                @if ($item['options'])
                                    <ul @class(['mt-2 gap-x-6 gap-y-1 text-sm', 'grid grid-cols-2' => $question->type !== QuestionType::MultipleChoice && $question->type !== QuestionType::Listening, 'space-y-1' => in_array($question->type, [QuestionType::MultipleChoice, QuestionType::Listening], true)])>
                                        @foreach ($item['options'] as $choice)
                                            @php
                                                $right = $withKey && $choice['option']->is_correct;
                                                $lettered = in_array($question->type, [QuestionType::MultipleChoice, QuestionType::Listening], true);
                                            @endphp
                                            <li @class(['flex items-start gap-2', 'font-bold text-woka dark:text-woka-light' => $right])>
                                                <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full border border-hutan/40 text-xs {{ $right ? 'border-woka bg-woka text-white' : '' }}">{{ $lettered ? $choice['label'] : '' }}</span>
                                                <span>@if ($lettered)<x-reading-text :text="$choice['option']->body" :reading="$choice['option']->reading" :display="$quiz->reading_display" />@else{{ $choice['label'] }}@endif @if ($right)✓@endif</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif ($question->type === QuestionType::Matching)
                                    <div class="mt-2 grid grid-cols-2 gap-x-6 text-sm">
                                        <ul class="space-y-1">
                                            @foreach ($question->options as $option)
                                                <li class="flex items-end gap-2"><x-reading-text :text="$option->body" :reading="$option->reading" :display="$quiz->reading_display" />
                                                    @if ($withKey)<span class="font-bold text-woka dark:text-woka-light">→ {{ $option->match_key }}</span>@else<span class="mb-1 flex-1 border-b border-dotted border-hutan/50"></span>@endif
                                                </li>
                                            @endforeach
                                        </ul>
                                        @unless ($withKey)
                                            <ul class="space-y-1 text-hutan/80 dark:text-santan/80">
                                                @foreach ($question->options->pluck('match_key')->sort()->values() as $key)
                                                    <li>• {{ $key }}</li>
                                                @endforeach
                                            </ul>
                                        @endunless
                                    </div>
                                @elseif ($withKey)
                                    @if ($item['key'])
                                        <p class="mt-2 whitespace-pre-line rounded-lg bg-woka-50 px-3 py-2 text-sm dark:bg-hutan-800"><strong>{{ $question->type->isAutoGraded() ? 'Kunci' : 'Pembahasan' }}:</strong> {{ $item['key'] }}</p>
                                    @endif
                                @else
                                    @foreach (range(1, $question->type === QuestionType::ShortAnswer ? 1 : 4) as $line)
                                        <div class="mt-5 border-b border-dotted border-hutan/50"></div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($results)
            <section @class(['mt-6', 'print:break-before-page' => $part === 'semua']) data-test="paper-results">
                <h2 class="font-display text-lg font-bold">Hasil</h2>
                <p class="mb-2 text-sm text-hutan/70 dark:text-santan/70">
                    {{ $results['graded'] }} dinilai · rata-rata {{ $results['average'] !== null ? angka($results['average'], 1) : '–' }}
                    · tertinggi {{ $results['highest'] !== null ? angka($results['highest'], 1) : '–' }}
                    · terendah {{ $results['lowest'] !== null ? angka($results['lowest'], 1) : '–' }}
                    · lulus (≥ {{ angka($quiz->passing_score) }}) {{ $results['passed'] }} peserta
                    @if ($results['missing']->isNotEmpty()) · belum mengerjakan {{ $results['missing']->count() }}@endif
                </p>
                <div class="overflow-x-auto print:overflow-visible">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-woka text-left text-xs uppercase">
                                <th class="py-1.5 pe-2">#</th><th class="py-1.5 pe-2">Nama</th><th class="py-1.5 pe-2">NIS</th><th class="py-1.5 pe-2 text-right">Skor</th>
                                <th class="py-1.5 pe-2 text-right">Nilai</th><th class="py-1.5 pe-2">Predikat</th><th class="py-1.5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-woka-100 dark:divide-hutan-800">
                            @foreach ($results['rows'] as $row)
                                @php
                                    $attempt = $row['attempt'];
                                @endphp
                                <tr wire:key="pr-{{ $attempt->id }}" class="break-inside-avoid">
                                    <td class="py-1.5 pe-2 font-bold">{{ $row['rank'] ?? '–' }}</td>
                                    <td class="py-1.5 pe-2 font-semibold">{{ $attempt->user->name }}</td>
                                    <td class="py-1.5 pe-2">{{ $attempt->user->username }}</td>
                                    <td class="whitespace-nowrap py-1.5 pe-2 text-right tabular-nums">{{ $attempt->score !== null ? poin($attempt->score).'/'.poin($attempt->max_score) : '–' }}</td>
                                    <td class="py-1.5 pe-2 text-right font-bold tabular-nums">{{ $attempt->percentage !== null ? angka($attempt->percentage, 1) : '–' }}</td>
                                    <td class="py-1.5 pe-2">{{ $grades->grade($attempt->percentage) ?? '–' }}</td>
                                    <td class="py-1.5">{{ $attempt->status->label() }}</td>
                                </tr>
                            @endforeach
                            @foreach ($results['missing'] as $user)
                                <tr wire:key="pm-{{ $user->id }}" class="text-hutan/60 dark:text-santan/60">
                                    <td class="py-1.5 pe-2">–</td><td class="py-1.5 pe-2">{{ $user->name }}</td><td class="py-1.5 pe-2">{{ $user->username }}</td>
                                    <td colspan="4" class="py-1.5">Belum mengerjakan</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <footer class="mt-8 flex justify-end text-center text-sm">
            <div class="w-56">
                <p>Manado, {{ tanggal(now()) }}</p>
                <p>Pengajar</p>
                <p class="mt-14 border-t border-hutan pt-1">{{ $quiz->teacher?->name ?? $quiz->classroom->teacher?->name }}</p>
            </div>
        </footer>
    </article>
</div>
