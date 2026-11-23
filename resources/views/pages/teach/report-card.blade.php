<?php

use App\Models\Classroom;
use App\Models\User;
use App\Services\Reports\ReportCard;
use App\Services\Reports\ScoreAnalytics;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rapor peserta')] class extends Component {
    public Classroom $classroom;

    public User $participant;

    public function mount(Classroom $classroom, User $user): void
    {
        $this->authorize('viewScores', $classroom);
        abort_unless($classroom->participants()->whereKey($user->id)->exists(), 404);
        $this->classroom = $classroom;
        $this->participant = $user;
    }

    public function with(ReportCard $cards): array
    {
        return ['card' => $cards->for($this->classroom, $this->participant)];
    }
}; ?>

<div class="mx-auto max-w-4xl">
    <x-cjk-fonts />
    <x-page-header :title="'Rapor · '.$participant->name" :back="route('teach.score-recap', ['tab' => 'rapor', 'kelas' => $classroom->id])"
                   :subtitle="$classroom->label().' · '.tanggal($card['from']).' – '.tanggal($card['to'])">
        <x-slot:actions>
            <flux:button icon="printer" x-on:click="window.print()" class="print:hidden">Cetak</flux:button>
            <flux:button variant="primary" icon="arrow-down-tray" :href="route('teach.report-card.pdf', [$classroom, $participant])" class="print:hidden">Unduh PDF</flux:button>
        </x-slot:actions>
    </x-page-header>

    @php
        $scores = $card['scores'];
        $pct = fn ($v) => $v === null ? '–' : angka($v, 1);
    @endphp

    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
        <x-card class="text-center" data-test="report-final">
            <p class="text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">Nilai akhir</p>
            <p class="font-display text-5xl font-extrabold">{{ $pct($scores['overall'] ?? null) }}</p>
            @if ($scores['grade'] ?? null)
                <flux:badge size="lg" :color="\App\Services\GradeService::color($scores['grade'])" class="mt-1">Predikat {{ $scores['grade'] }}</flux:badge>
            @endif
            <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Rata-rata komponen yang sudah ada nilainya.</p>
        </x-card>
        <x-card>
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light">Komponen nilai</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach (ScoreAnalytics::SOURCES as $key => $label)
                    <div class="rounded-xl bg-santan p-2.5 text-center dark:bg-hutan-950">
                        <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $label }}</p>
                        <p class="font-display text-2xl font-bold">{{ $pct($scores[$key] ?? null) }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <p>Kehadiran harian: <strong>{{ $card['attendance']['rate'] !== null ? angka($card['attendance']['rate'], 1).'%' : '–' }}</strong></p>
                <p>Kehadiran sesi: <strong>{{ $card['sessionAttendance']['rate'] !== null ? angka($card['sessionAttendance']['rate'], 1).'%' : '–' }}</strong> ({{ $card['sessionAttendance']['count'] }} sesi)</p>
            </div>
        </x-card>
    </div>

    @if ($card['nGain']['pretest']->isNotEmpty() || $card['nGain']['posttest']->isNotEmpty())
        @php
            $gain = $card['nGain']['row'];
        @endphp
        <x-card class="mt-3" title="Pretest – posttest">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div><p class="text-xs text-hutan/60 dark:text-santan/60">Pretest</p><p class="font-display text-2xl font-bold">{{ $pct($gain['pre'] ?? null) }}</p></div>
                <div><p class="text-xs text-hutan/60 dark:text-santan/60">Posttest</p><p class="font-display text-2xl font-bold">{{ $pct($gain['post'] ?? null) }}</p></div>
                <div><p class="text-xs text-hutan/60 dark:text-santan/60">N-Gain</p><p class="font-display text-2xl font-bold">{{ ($gain['gain'] ?? null) !== null ? angka($gain['gain'], 2) : '–' }}</p><p class="text-xs">{{ $gain['category'] ?? '' }}</p></div>
            </div>
            <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Jumlah poin semua quiz pretest ({{ $card['nGain']['pretest']->count() }}) dan posttest ({{ $card['nGain']['posttest']->count() }}) kelas ini.@if ($gain['pending']) Masih ada esai yang belum selesai dinilai, jadi nilainya sementara.@endif</p>
        </x-card>
    @endif

    <x-card class="mt-3" title="Quiz & ujian" :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-woka-100 text-left text-xs uppercase text-hutan/50 dark:border-hutan-800 dark:text-santan/50"><th class="px-4 py-2">Quiz</th><th class="px-2 py-2">Jenis</th><th class="px-2 py-2 text-right">Poin</th><th class="px-4 py-2 text-right">Nilai</th></tr></thead>
                <tbody class="divide-y divide-woka-100 dark:divide-hutan-800">
                    @forelse ($card['quizzes'] as $item)
                        <tr wire:key="rq-{{ $item['quiz']->id }}">
                            <td class="px-4 py-2 font-semibold">{{ $item['quiz']->title }}</td>
                            <td class="px-2 py-2"><x-status-badge :status="$item['quiz']->type" /></td>
                            <td class="px-2 py-2 text-right tabular-nums">{{ $item['attempt'] ? poin($item['attempt']->score).'/'.poin($item['attempt']->max_score) : '–' }}</td>
                            <td class="px-4 py-2 text-right font-bold tabular-nums">
                                @if (! $item['attempt'])<span class="font-normal text-rica">belum</span>
                                @elseif ($item['attempt']->percentage === null)<span class="font-normal text-kunyit-700 dark:text-kunyit">menunggu dinilai</span>
                                @else {{ angka($item['attempt']->percentage, 1) }} @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-3 text-center text-hutan/60 dark:text-santan/60">Belum ada quiz.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card class="mt-3" title="Tugas" :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-woka-100 text-left text-xs uppercase text-hutan/50 dark:border-hutan-800 dark:text-santan/50"><th class="px-4 py-2">Tugas</th><th class="px-2 py-2">Tenggat</th><th class="px-4 py-2 text-right">Nilai</th></tr></thead>
                <tbody class="divide-y divide-woka-100 dark:divide-hutan-800">
                    @forelse ($card['assignments'] as $item)
                        <tr wire:key="ra-{{ $item['assignment']->id }}">
                            <td class="px-4 py-2 font-semibold">{{ $item['assignment']->title }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">{{ $item['assignment']->due_at ? tanggal($item['assignment']->due_at, 'j M') : '–' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                @if (! $item['submission'])<span class="text-rica">belum mengumpulkan</span>
                                @elseif ($item['submission']->percentage() === null)<span class="text-kunyit-700 dark:text-kunyit">menunggu dinilai</span>
                                @else <strong>{{ angka($item['submission']->percentage(), 1) }}</strong>@if ($item['submission']->is_late) <span class="text-xs text-kunyit-700 dark:text-kunyit">terlambat</span>@endif @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-3 text-center text-hutan/60 dark:text-santan/60">Belum ada tugas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card class="mt-3" title="Penilaian periodik">
        @forelse ($card['periodic'] as $assessment)
            <div wire:key="rp-{{ $assessment->id }}" class="border-b border-woka-100 py-2 last:border-0 dark:border-hutan-800">
                <p class="text-sm"><strong>{{ $assessment->periodLabel() }}</strong> · rata-rata {{ angka($assessment->average, 1) }} · predikat {{ $assessment->grade }}</p>
                <div class="mt-1 flex flex-wrap gap-1 text-xs">
                    @foreach ($assessment->orderedScores() as $score)
                        <span class="rounded-full bg-santan px-2 py-0.5 dark:bg-hutan-800">{{ $score->aspect->name }} {{ angka($score->score) }}</span>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada penilaian periodik yang terbit.</p>
        @endforelse
    </x-card>
</div>
