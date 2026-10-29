<?php

use App\Enums\EvaluationQuestionType;
use App\Models\ProgramEvaluation;
use App\Services\Evaluations\EvaluationResults;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hasil survei')] class extends Component {
    public ProgramEvaluation $evaluation;

    public function mount(ProgramEvaluation $evaluation): void
    {
        $this->authorize('view', $evaluation);
        $this->evaluation = $evaluation->load(['batches', 'questions']);
    }

    /** Kategori indeks kepuasan. @return array{0: string, 1: string} */
    public function indexLabel(?float $index): array
    {
        return match (true) {
            $index === null => ['Belum ada data', 'zinc'],
            $index >= 80 => ['Sangat baik', 'green'],
            $index >= 60 => ['Baik', 'teal'],
            $index >= 40 => ['Cukup', 'amber'],
            default => ['Perlu perbaikan', 'red'],
        };
    }

    public function with(EvaluationResults $results): array
    {
        return [
            'summary' => $results->summary($this->evaluation),
            'likertLabels' => EvaluationQuestionType::likertLabels(),
            'canManage' => auth()->user()->can('evaluation.manage'),
        ];
    }
}; ?>

<div>
    @php([$stateLabel, $stateColor] = $evaluation->state())
    <x-page-header :title="$evaluation->title" :back="route('manage.evaluations.index')"
                   :subtitle="$evaluation->category->label().' · '.$evaluation->batches->pluck('name')->join(', ').' · tenggat '.tanggal_jam($evaluation->closes_at)">
        @if ($canManage)
            <x-slot:actions>
                <flux:button icon="pencil-square" :href="route('manage.evaluations.edit', $evaluation)" wire:navigate>Ubah</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-1">
        <flux:badge size="sm" :color="$stateColor">{{ $stateLabel }}</flux:badge>
        @if ($evaluation->is_anonymous)
            <flux:badge size="sm" icon="eye-slash">Anonim — nama responden tidak disimpan</flux:badge>
        @endif
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-card class="min-w-0">
            <p class="text-sm text-hutan/60 dark:text-santan/60">Partisipasi</p>
            <p class="font-display text-3xl font-extrabold">{{ $summary['rate'] !== null ? $summary['rate'].'%' : '–' }}</p>
            <p class="text-sm">{{ $summary['responded'] }} dari {{ $summary['target'] }} responden</p>
        </x-card>
        <x-card class="min-w-0">
            @php([$label, $color] = $this->indexLabel($summary['index']))
            <p class="text-sm text-hutan/60 dark:text-santan/60">Indeks kepuasan (0–100)</p>
            <p class="font-display text-3xl font-extrabold" data-test="satisfaction-index">{{ $summary['index'] ?? '–' }}</p>
            <flux:badge size="sm" :color="$color">{{ $label }}</flux:badge>
        </x-card>
        <x-card class="min-w-0">
            <p class="mb-1 text-sm text-hutan/60 dark:text-santan/60">Per kelompok</p>
            @foreach ($summary['roles'] as $role => $row)
                <div class="flex justify-between text-sm">
                    <span>{{ $role === 'participant' ? 'Peserta' : 'Pengajar' }}</span>
                    <span class="font-semibold">{{ $row['responded'] }}/{{ $row['target'] }}</span>
                </div>
            @endforeach
        </x-card>
    </div>

    @if ($summary['aspects'])
        <x-card title="Per aspek" subtitle="Diurutkan dari skor terendah: prioritas perbaikan di atas." class="mb-4">
            <ul class="space-y-2">
                @foreach ($summary['aspects'] as $aspect)
                    <li class="text-sm">
                        <div class="mb-1 flex justify-between gap-2">
                            <span class="min-w-0 truncate font-semibold">{{ $aspect['aspect'] }}</span>
                            <span class="shrink-0">{{ number_format($aspect['average'], 2, ',', '.') }} / 5 · <strong>{{ $aspect['index'] }}</strong></span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800">
                            <div @class(['h-full rounded-full', 'bg-red-500' => $aspect['index'] < 40, 'bg-amber-500' => $aspect['index'] >= 40 && $aspect['index'] < 60, 'bg-woka' => $aspect['index'] >= 60]) style="width: {{ $aspect['index'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Per pertanyaan</h2>
    <div class="space-y-3">
        @foreach ($summary['questions'] as $i => $row)
            @php($question = $row['question'])
            <x-card wire:key="qr-{{ $question->id }}" class="min-w-0">
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs text-hutan/60 dark:text-santan/60">#{{ $i + 1 }} · {{ $question->aspect ?: 'Umum' }} · {{ $question->audience->label() }}</p>
                        <p class="font-semibold">{{ $question->body }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-hutan/60 dark:text-santan/60">{{ $row['count'] }} jawaban</span>
                </div>

                @if ($question->type === EvaluationQuestionType::Likert)
                    <p class="mb-2 text-sm">Rata-rata <strong>{{ $row['average'] !== null ? number_format($row['average'], 2, ',', '.') : '–' }}</strong> / 5</p>
                    @php($max = max(1, max($row['distribution'])))
                    <div class="space-y-1">
                        @foreach (array_reverse($row['distribution'], true) as $score => $count)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-24 shrink-0">{{ $score }} · {{ $likertLabels[$score] }}</span>
                                <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800"><div class="h-full rounded-full bg-kunyit" style="width: {{ round($count / $max * 100) }}%"></div></div>
                                <span class="w-6 shrink-0 text-end">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @elseif ($question->type === EvaluationQuestionType::Choice)
                    @php($max = max(1, max($row['choices'] ?: [0])))
                    <div class="space-y-1">
                        @foreach ($row['choices'] as $option => $count)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-28 shrink-0 truncate">{{ $option }}</span>
                                <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800"><div class="h-full rounded-full bg-langit" style="width: {{ round($count / $max * 100) }}%"></div></div>
                                <span class="w-6 shrink-0 text-end">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    @forelse ($row['texts'] as $text)
                        <blockquote class="mb-2 border-s-4 border-woka-100 ps-3 text-sm dark:border-hutan-700">
                            {{ $text['text'] }}
                            @if ($text['author'])
                                <span class="block text-xs text-hutan/60 dark:text-santan/60">— {{ $text['author'] }}</span>
                            @endif
                        </blockquote>
                    @empty
                        <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada isian.</p>
                    @endforelse
                @endif
            </x-card>
        @endforeach
    </div>
</div>
