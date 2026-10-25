<?php

use App\Livewire\Concerns\ResolvesGuardianChild;
use App\Services\GradeReport;
use App\Services\GuardianOverview;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nilai & catatan anak')] class extends Component {
    use ResolvesGuardianChild;

    public function with(GradeReport $grades, GuardianOverview $overview): array
    {
        $child = $this->child();
        // guardianView: hanya penilaian & catatan yang ditandai boleh dilihat wali.
        $report = $child ? $grades->for($child, guardianView: true) : null;

        return [
            'children' => $this->children(),
            'child' => $child,
            'report' => $report,
            'assignmentRate' => $child ? $overview->assignmentRate($child) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Nilai & catatan" subtitle="Penilaian periodik, pretest–posttest, tugas, dan catatan pengajar." />

    @if (! $child)
        <x-empty-state icon="link" title="Belum ada anak terhubung" description="Tautkan anak terlebih dulu di menu Hubungkan Anak." />
    @else
        <x-guardian.child-switcher :children="$children" :child="$child" />

        @php($latest = $report['latest'])
        <div class="mb-4 grid grid-cols-2 gap-3">
            <x-stat label="Nilai" :value="$latest ? angka($latest->average).' · '.$latest->grade : '–'" :hint="$latest ? 'terakhir' : 'belum ada'" icon="chart-bar" />
            <x-stat label="Tugas" hint="masuk" :value="$assignmentRate !== null ? $assignmentRate.'%' : '–'" icon="clipboard-document-check" tone="kunyit" />
        </div>

        @if ($latest)
            <x-card title="Perkembangan per aspek" class="mb-4">
                <div class="h-56" wire:ignore x-data="chart(@js($report['lineChart']))"><canvas x-ref="canvas"></canvas></div>
            </x-card>
        @endif

        @if ($ngain = $report['sections']['quizzes']['ngain'] ?? null)
            <x-card title="Pretest – posttest" class="mb-4">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div><p class="text-xs text-hutan/60 dark:text-santan/60">Pretest</p><p class="font-display text-2xl font-bold">{{ $ngain['pre'] !== null ? angka($ngain['pre']) : '–' }}</p></div>
                    <div><p class="text-xs text-hutan/60 dark:text-santan/60">Posttest</p><p class="font-display text-2xl font-bold">{{ $ngain['post'] !== null ? angka($ngain['post']) : '–' }}</p></div>
                    <div><p class="text-xs text-hutan/60 dark:text-santan/60">Peningkatan</p><p class="font-display text-2xl font-bold text-woka">{{ $ngain['gain'] !== null ? angka($ngain['gain'] * 100).'%' : '–' }}</p></div>
                </div>
                @if ($ngain['category'] ?? null)
                    <p class="mt-2 text-center text-sm">Kategori N-Gain: <strong>{{ $ngain['category'] }}</strong></p>
                @endif
            </x-card>
        @endif

        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Penilaian & catatan pengajar</h2>
        <div class="space-y-3">
            @forelse ($report['timeline'] as $entry)
                @php($item = $entry['item'])
                <x-card wire:key="tl-{{ $entry['type'] }}-{{ $item->id }}">
                    @if ($entry['type'] === 'assessment')
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">Penilaian {{ $item->periodLabel() }}</p>
                                <p class="text-sm text-hutan/60 dark:text-santan/60">Rata-rata {{ angka($item->average) }}</p>
                            </div>
                            <flux:badge :color="\App\Services\GradeService::color($item->grade)" size="lg">{{ $item->grade }}</flux:badge>
                        </div>
                        @if ($item->feedback)
                            <p class="mt-2 text-sm">{{ $item->feedback }}</p>
                        @endif
                    @else
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($item->created_at) }} · {{ $item->teacher?->name }}@if ($item->aspect) · {{ $item->aspect->label() }}@endif</p>
                        <p class="mt-1 text-sm">{{ $item->note }}</p>
                    @endif
                </x-card>
            @empty
                <x-empty-state icon="chart-bar" title="Belum ada penilaian" description="Penilaian dan catatan yang dibagikan pengajar akan tampil di sini." />
            @endforelse
        </div>
    @endif
</div>
