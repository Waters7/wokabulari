<?php

use App\Models\Assessment;
use App\Models\SessionNote;
use App\Services\GradeReport;
use App\Services\GradeService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nilai & masukan')] class extends Component {
    /**
     * Tandai catatan & penilaian sebagai dibaca. Dipanggil dari browser setelah halaman tampil,
     * sehingga badge "baru" tetap terlihat pada kunjungan ini.
     */
    public function markRead(): void
    {
        $this->skipRender();
        $user = auth()->user();
        SessionNote::query()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
        Assessment::query()->published()->where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function with(): array
    {
        return app(GradeReport::class)->for(auth()->user());
    }
}; ?>

<div class="mx-auto max-w-4xl" x-init="setTimeout(() => $wire.markRead(), 1500)">
    <x-page-header title="Nilai & masukan" subtitle="Perkembangan per aspek, penilaian periodik, dan catatan pengajar." />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="text-center">
            <p class="text-xs font-semibold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Penilaian terakhir</p>
            @if ($latest)
                <p class="mt-1 font-display text-5xl font-extrabold text-woka">{{ angka($latest->average, 1) }}</p>
                <flux:badge :color="GradeService::color($latest->grade)" size="lg">Predikat {{ $latest->grade }}</flux:badge>
                <p class="mt-2 text-sm text-hutan/60 dark:text-santan/60">{{ $latest->periodLabel() }}</p>
            @else
                <p class="mt-3 text-sm text-hutan/60 dark:text-santan/60">Belum ada penilaian terbit.</p>
            @endif
        </x-card>

        <x-card class="lg:col-span-2" title="Profil aspek">
            @if ($radarChart)
                <div class="h-64" x-data="chart(@js($radarChart))" wire:ignore><canvas x-ref="canvas"></canvas></div>
            @else
                <x-empty-state icon="chart-pie" title="Grafik muncul setelah penilaian pertama terbit" />
            @endif
        </x-card>
    </div>

    @if ($assessments->count() > 1)
        <x-card class="mt-4" title="Perkembangan per aspek">
            <div class="h-72" x-data="chart(@js($lineChart))" wire:ignore><canvas x-ref="canvas"></canvas></div>
        </x-card>
    @endif

    @foreach ($sections as $key => $section)
        @includeIf('partials.grades.'.$key, ['section' => $section])
    @endforeach

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-card title="Catatan & penilaian" :padding="false">
            <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
                @forelse ($timeline as $entry)
                    <li class="px-4 py-3" wire:key="tl-{{ $entry['type'] }}-{{ $entry['item']->id }}">
                        @if ($entry['type'] === 'assessment')
                            @php($a = $entry['item'])
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold">Penilaian {{ strtolower($a->period_type->label()) }} · {{ $a->periodLabel() }}</p>
                                    <p class="text-sm">Rata-rata {{ angka($a->average, 1) }} · predikat {{ $a->grade }}</p>
                                    <div class="mt-1 flex flex-wrap gap-1 text-xs">
                                        @foreach (\App\Enums\LanguageAspect::periodic() as $aspect)
                                            @if ($a->{$aspect->value} !== null)
                                                <span class="rounded-full bg-santan px-2 py-0.5 dark:bg-hutan-800">{{ $aspect->label() }} {{ angka($a->{$aspect->value}) }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if ($a->feedback)
                                        <p class="mt-2 whitespace-pre-line text-sm italic">“{{ $a->feedback }}”</p>
                                    @endif
                                </div>
                                @if ($entry['unread'])<flux:badge size="sm" color="amber">Baru</flux:badge>@endif
                            </div>
                        @else
                            @php($n = $entry['item'])
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($n->created_at) }} · {{ $n->session->topic }} · {{ $n->teacher?->name }}</p>
                                    @if ($n->aspect)<x-status-badge :status="$n->aspect" />@endif
                                    <p class="mt-1 whitespace-pre-line text-sm">{{ $n->note }}</p>
                                </div>
                                @if ($entry['unread'])<flux:badge size="sm" color="amber">Ada catatan baru</flux:badge>@endif
                            </div>
                        @endif
                    </li>
                @empty
                    <li><x-empty-state class="m-4" icon="chat-bubble-left-ellipsis" title="Belum ada catatan atau penilaian" /></li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Nilai per sesi" :padding="false">
            <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
                @forelse ($sessionScores as $row)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $row['session']->topic }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($row['session']->date) }}</p>
                        </div>
                        <span class="font-display text-xl font-bold">{{ $row['score'] !== null ? angka($row['score'], 1) : '–' }}</span>
                    </li>
                @empty
                    <li><x-empty-state class="m-4" icon="chart-bar" title="Belum ada nilai sesi" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
