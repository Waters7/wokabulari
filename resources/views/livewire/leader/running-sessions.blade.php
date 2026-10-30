<?php

use App\Services\Reports\RunningSessions;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Panel "Sesi berjalan" di dashboard eksekutif. Data langsung (tanpa cache), diperbarui tiap menit.
 */
new class extends Component {
    #[Reactive]
    public ?int $batchId = null;

    #[Reactive]
    public ?int $classroomId = null;

    public function with(RunningSessions $running): array
    {
        return $running->overview($this->batchId, $this->classroomId);
    }
}; ?>

<div wire:poll.60s.visible class="mb-4 rounded-2xl bg-white p-4 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800" data-test="running-sessions">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <p class="flex items-center gap-2 font-semibold">
            @if ($sessions !== [])
                <span class="relative flex size-2.5" aria-hidden="true">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-woka-light opacity-75"></span>
                    <span class="relative inline-flex size-2.5 rounded-full bg-woka"></span>
                </span>
            @else
                <span class="inline-flex size-2.5 rounded-full bg-zinc-300 dark:bg-hutan-800" aria-hidden="true"></span>
            @endif
            Sesi berjalan ({{ count($sessions) }})
        </p>
        <p class="text-xs text-hutan/60 dark:text-santan/60">
            Hari ini {{ $today['scheduled'] }} terjadwal · {{ $today['ongoing'] }} berjalan · {{ $today['completed'] }} selesai · pukul {{ now()->format('H.i') }}
        </p>
    </div>

    @if ($sessions === [])
        <p class="mt-2 text-sm text-hutan/60 dark:text-santan/60">Tidak ada sesi kelas yang sedang berjalan saat ini.</p>
    @else
        <ul class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($sessions as $row)
                @php
                    $s = $row['session'];
                    $rate = $row['participants'] > 0 ? min(100, round($row['attended'] / $row['participants'] * 100)) : 0;
                @endphp
                <li wire:key="run-{{ $s->id }}">
                    <a href="{{ route('teach.sessions.show', $s) }}" wire:navigate
                       class="flex gap-3 rounded-xl p-3 ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:ring-hutan-800">
                        <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-woka-50 dark:bg-hutan-800">
                            @if ($row['photo'])
                                <img src="{{ $row['photo'] }}" alt="Foto kelas {{ $s->classroom->name }}" class="size-full object-cover" loading="lazy">
                            @else
                                <span class="text-2xl" aria-hidden="true">{{ $s->classroom->language?->flag() ?? '🎓' }}</span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $s->classroom->label() }}</span>
                            <span class="block truncate text-xs text-hutan/60 dark:text-santan/60">{{ $s->topic }} · {{ $row['teacher'] ?? '–' }}</span>
                            <span class="mt-1 flex flex-wrap gap-x-2.5 gap-y-0.5 text-xs">
                                <span>Mulai {{ jam($s->started_at) }}@if ($row['minutes'] !== null) · berjalan {{ durasi($row['minutes']) }}@endif</span>
                                <span class="font-semibold tabular-nums">Hadir {{ $row['attended'] }}/{{ $row['participants'] }}</span>
                                @if ($row['codeActive'])
                                    <span class="font-semibold text-woka dark:text-woka-light">Kode aktif</span>
                                @endif
                                @if ($row['overtime'] > 0)
                                    <span class="font-semibold text-kunyit-700 dark:text-kunyit">Lewat jadwal {{ durasi($row['overtime']) }}</span>
                                @endif
                                @if ($row['missingDoc'])
                                    <span class="font-semibold text-rica">Belum ada dokumentasi</span>
                                @endif
                            </span>
                            <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-hutan-800" aria-hidden="true">
                                <span class="block h-full rounded-full bg-woka" style="width: {{ $rate }}%"></span>
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
