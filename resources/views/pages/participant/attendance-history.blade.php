<?php

use App\Enums\AttendanceStatus;
use App\Models\DailyAttendance;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Riwayat absen')] class extends Component {
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function shift(int $delta): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth()->addMonths($delta)->format('Y-m');
    }

    public function with(): array
    {
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = DailyAttendance::query()
            ->where('user_id', auth()->id())
            ->whereDateBetween('date', $start->toDateString(), $end->toDateString())
            ->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        $counts = collect(AttendanceStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $records->where('status', $s)->count()]);
        $recorded = $records->whereNotIn('status', [AttendanceStatus::Rejected])->count();

        return [
            'start' => $start,
            'days' => collect(range(0, $end->day - 1))->map(fn ($i) => $start->copy()->addDays($i)),
            'records' => $records,
            'counts' => $counts,
            'percentage' => $recorded > 0 ? round(($counts['present'] + $counts['late']) / $recorded * 100) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Riwayat absen" :back="route('attendance.daily')" />

    <div class="mb-4 flex items-center justify-between">
        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Bulan sebelumnya" />
        <p class="font-display text-lg font-bold">{{ tanggal($start, 'F Y') }}</p>
        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Bulan berikutnya" />
    </div>

    <div class="mb-4 grid grid-cols-3 gap-2 sm:grid-cols-6">
        @foreach ([AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Excused, AttendanceStatus::Sick, AttendanceStatus::Absent] as $status)
            <div class="rounded-xl bg-white p-2 text-center ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800">
                <p class="font-display text-xl font-bold">{{ $counts[$status->value] }}</p>
                <p class="text-xs"><x-status-badge :status="$status" /></p>
            </div>
        @endforeach
        <div class="rounded-xl bg-woka p-2 text-center text-white">
            <p class="font-display text-xl font-bold">{{ $percentage !== null ? $percentage.'%' : '–' }}</p>
            <p class="text-xs">Kehadiran</p>
        </div>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($days as $day)
                @php($record = $records[$day->toDateString()] ?? null)
                @continue($day->isFuture() && ! $record)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5" wire:key="d-{{ $day->toDateString() }}">
                    <div>
                        <p class="text-sm font-semibold {{ $day->isWeekend() ? 'text-hutan/50' : '' }}">{{ tanggal($day, 'l, j M') }}</p>
                        @if ($record?->reject_reason && $record->status === AttendanceStatus::Rejected)
                            <p class="text-xs text-rica">{{ $record->reject_reason }}</p>
                        @elseif ($record?->checked_in_at)
                            <p class="text-xs text-hutan/60 dark:text-santan/60">Pukul {{ jam($record->checked_in_at) }}</p>
                        @endif
                    </div>
                    @if ($record)
                        <x-status-badge :status="$record->status" />
                    @elseif (! $day->isWeekend() && ! $day->isToday())
                        <flux:badge size="sm" color="zinc">Tidak ada data</flux:badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
