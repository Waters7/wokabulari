<?php

use App\Livewire\Concerns\ResolvesGuardianChild;
use App\Services\GuardianOverview;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Kehadiran anak')] class extends Component {
    use ResolvesGuardianChild;

    #[Url(as: 'bulan')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function shift(int $months): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonths($months)->format('Y-m');
    }

    public function with(GuardianOverview $overview): array
    {
        $child = $this->child();
        $start = CarbonImmutable::parse($this->month.'-01');
        $calendar = $child ? $overview->calendar($child, $start) : collect();

        return [
            'children' => $this->children(),
            'child' => $child,
            'start' => $start,
            'calendar' => $calendar,
            'classCounts' => $calendar->pluck('class')->filter()->countBy(fn ($s) => $s->value),
            'dormCounts' => $calendar->pluck('dorm')->filter()->countBy(fn ($s) => $s->value),
            'rate' => $child ? $overview->attendanceRate($child, $start, $start->endOfMonth()) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    @php
        $dot = fn ($status) => match ($status?->value) {
            'present', 'confirmed' => 'bg-woka',
            'late', 'pending_confirmation' => 'bg-kunyit',
            'excused', 'on_leave' => 'bg-sky-500',
            'sick', 'sick_in_room' => 'bg-violet-500',
            'absent', 'not_present' => 'bg-rica',
            default => 'bg-zinc-300 dark:bg-hutan-800',
        };
    @endphp

    <x-page-header title="Kehadiran" subtitle="Absen kelas harian dan absen asrama malam." />

    @if (! $child)
        <x-empty-state icon="link" title="Belum ada anak terhubung" description="Tautkan anak terlebih dulu di menu Hubungkan Anak." />
    @else
        <x-guardian.child-switcher :children="$children" :child="$child" />

        <div class="mb-3 flex items-center justify-between">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Bulan sebelumnya" />
            <p class="font-display text-lg font-bold">{{ tanggal($start, 'F Y') }}</p>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Bulan berikutnya" />
        </div>

        <x-card class="mb-4">
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-hutan/60 dark:text-santan/60">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)
                    <span>{{ $d }}</span>
                @endforeach
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1">
                @for ($i = 1; $i < $start->dayOfWeekIso; $i++)
                    <span></span>
                @endfor
                @foreach ($calendar as $key => $day)
                    <div @class(['flex aspect-square flex-col items-center justify-center rounded-xl text-sm', 'ring-2 ring-woka' => $day['date']->isToday(), 'bg-santan dark:bg-hutan-800' => ! $day['date']->isToday()])
                         title="Kelas: {{ $day['class']?->label() ?? '-' }} · Asrama: {{ $day['dorm']?->label() ?? '-' }}" wire:key="cal-{{ $key }}">
                        <span class="font-semibold">{{ $day['date']->day }}</span>
                        <span class="mt-0.5 flex gap-0.5">
                            <span class="size-2 rounded-full {{ $dot($day['class']) }}" aria-label="Kelas {{ $day['class']?->label() ?? 'tidak ada data' }}"></span>
                            <span class="size-2 rounded-sm {{ $dot($day['dorm']) }}" aria-label="Asrama {{ $day['dorm']?->label() ?? 'tidak ada data' }}"></span>
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-woka"></span> Hadir</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-kunyit"></span> Terlambat/menunggu</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-sky-500"></span> Izin</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-violet-500"></span> Sakit</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-rica"></span> Alpa/tidak di asrama</span>
            </div>
            <p class="mt-2 text-xs text-hutan/60 dark:text-santan/60">Titik bulat = kelas, titik kotak = asrama malam.</p>
        </x-card>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-card title="Kelas bulan ini" :subtitle="$rate !== null ? 'Kehadiran '.$rate.'%' : null">
                @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                    @continue($status === \App\Enums\AttendanceStatus::Rejected)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <x-status-badge :status="$status" />
                        <span class="font-semibold">{{ $classCounts[$status->value] ?? 0 }} hari</span>
                    </div>
                @endforeach
            </x-card>
            <x-card title="Asrama bulan ini">
                @foreach (\App\Enums\DormAttendanceStatus::cases() as $status)
                    @continue($status === \App\Enums\DormAttendanceStatus::Rejected)
                    <div class="flex items-center justify-between py-1 text-sm">
                        <x-status-badge :status="$status" />
                        <span class="font-semibold">{{ $dormCounts[$status->value] ?? 0 }} malam</span>
                    </div>
                @endforeach
            </x-card>
        </div>
    @endif
</div>
