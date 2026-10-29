<?php

use App\Actions\Dorm\ConfirmDormAttendance;
use App\Enums\DormAttendanceStatus;
use App\Models\DormAttendance;
use App\Models\Dormitory;
use App\Models\User;
use App\Services\DormRoster;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Absen asrama malam')] class extends Component {
    #[Url]
    public ?int $dormitoryId = null;

    #[Url]
    public string $date = '';

    #[Url]
    public string $filter = 'all';

    public bool $showNote = false;

    public ?int $noteUserId = null;

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('viewAny', DormAttendance::class);
        $this->date = $this->date ?: today()->toDateString();
        $this->dormitoryId ??= Dormitory::query()->orderBy('name')->value('id');
    }

    public function confirm(int $attendanceId, ConfirmDormAttendance $confirm): void
    {
        $this->authorize('confirm', DormAttendance::class);
        $confirm->confirmMany(auth()->user(), [$attendanceId]);
    }

    public function confirmRoom(int $roomId, DormRoster $roster, ConfirmDormAttendance $confirm): void
    {
        $this->authorize('confirm', DormAttendance::class);
        $ids = $this->rows($roster)->filter(fn ($r) => $r['room']?->id === $roomId && $r['attendance']?->status === DormAttendanceStatus::PendingConfirmation)
            ->map(fn ($r) => $r['attendance']->id)->values()->all();
        $count = $confirm->confirmMany(auth()->user(), $ids);
        Flux::toast(variant: 'success', text: "{$count} absen dikonfirmasi.");
    }

    /** Apel manual: tandai hadir tanpa absen mandiri. */
    public function present(int $userId, ConfirmDormAttendance $confirm): void
    {
        $this->authorize('confirm', DormAttendance::class);
        $confirm->mark(auth()->user(), User::query()->findOrFail($userId), $this->date, DormAttendanceStatus::Confirmed, 'Apel manual');
    }

    public function askNotPresent(int $userId): void
    {
        $this->authorize('confirm', DormAttendance::class);
        $this->resetErrorBag();
        $this->noteUserId = $userId;
        $this->note = '';
        $this->showNote = true;
    }

    public function notPresent(ConfirmDormAttendance $confirm): void
    {
        $this->authorize('confirm', DormAttendance::class);

        try {
            $confirm->mark(auth()->user(), User::query()->findOrFail($this->noteUserId), $this->date, DormAttendanceStatus::NotPresent, $this->note);
        } catch (\InvalidArgumentException $e) {
            $this->addError('note', $e->getMessage());

            return;
        }

        $this->showNote = false;
        Flux::toast(text: 'Ditandai tidak di asrama.');
    }

    protected function rows(DormRoster $roster)
    {
        $dormitory = Dormitory::query()->find($this->dormitoryId);

        return $dormitory ? $roster->night($dormitory, CarbonImmutable::parse($this->date)) : collect();
    }

    public function with(DormRoster $roster): array
    {
        $rows = $this->rows($roster);
        $dormitory = Dormitory::query()->find($this->dormitoryId);
        $counts = [
            'all' => $rows->count(),
            'pending' => $rows->where('status', DormAttendanceStatus::PendingConfirmation)->count(),
            'confirmed' => $rows->where('status', DormAttendanceStatus::Confirmed)->count(),
            'missing' => $rows->filter(fn ($r) => $r['status'] === null || $r['status'] === DormAttendanceStatus::Rejected || $r['status'] === DormAttendanceStatus::NotPresent)->count(),
            'away' => $rows->filter(fn ($r) => in_array($r['status'], [DormAttendanceStatus::OnLeave, DormAttendanceStatus::SickInRoom], true))->count(),
        ];

        $filtered = $rows->filter(fn ($r) => match ($this->filter) {
            'pending' => $r['status'] === DormAttendanceStatus::PendingConfirmation,
            'confirmed' => $r['status'] === DormAttendanceStatus::Confirmed,
            'missing' => $r['status'] === null || $r['status'] === DormAttendanceStatus::Rejected || $r['status'] === DormAttendanceStatus::NotPresent,
            'away' => in_array($r['status'], [DormAttendanceStatus::OnLeave, DormAttendanceStatus::SickInRoom], true),
            default => true,
        });

        return [
            'dormitories' => Dormitory::query()->orderBy('name')->get(),
            'dormitory' => $dormitory,
            'groups' => $filtered->groupBy(fn ($r) => $r['room']?->number ?? '-')->sortKeys(),
            'counts' => $counts,
            'pastCurfew' => $dormitory && now()->gt($dormitory->curfewAt(CarbonImmutable::parse($this->date))),
            'noteUser' => $this->noteUserId ? User::query()->find($this->noteUserId) : null,
            'autoConfirm' => \App\Actions\Dorm\ConfirmDormAttendance::autoEnabled(),
        ];
    }
}; ?>

<div wire:poll.15s>
    <x-page-header title="Absen asrama malam" subtitle="Konfirmasi absen mandiri, apel manual, dan tandai yang tidak di asrama.">
        <x-slot:actions>
            <flux:input type="date" wire:model.live="date" size="sm" class="max-w-40" aria-label="Tanggal" />
        </x-slot:actions>
    </x-page-header>

    @if ($autoConfirm)
        <flux:callout icon="bolt" color="green" class="mb-3" data-test="dorm-auto-confirm"
                      heading="Konfirmasi otomatis aktif"
                      text="Absen mandiri yang lolos verifikasi wajah & lokasi langsung terkonfirmasi. Apel manual dan penandaan tetap bisa dilakukan. Matikan di Pengaturan Sistem untuk konfirmasi manual." />
    @endif

    <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 pb-1">
        @foreach ($dormitories as $item)
            <button type="button" wire:click="$set('dormitoryId', {{ $item->id }})" wire:key="d-{{ $item->id }}"
                    @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold ring-1',
                        'bg-woka text-white ring-woka' => $dormitory?->id === $item->id,
                        'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $dormitory?->id !== $item->id])>
                {{ $item->name }}
            </button>
        @endforeach
    </div>

    @if ($dormitory)
        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
            @foreach (['all' => 'Semua', 'pending' => 'Menunggu', 'confirmed' => 'Terkonfirmasi', 'missing' => 'Belum/tidak ada', 'away' => 'Izin/sakit'] as $key => $label)
                <button type="button" wire:click="$set('filter', '{{ $key }}')"
                        @class(['rounded-2xl p-3 text-start ring-1 transition',
                            'bg-woka-50 ring-woka dark:bg-hutan-800' => $filter === $key,
                            'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $filter !== $key,
                            'col-span-2 sm:col-span-1' => $key === 'all'])>
                    <p @class(['font-display text-2xl font-bold', 'text-rica' => $key === 'missing' && $counts['missing'] > 0 && $pastCurfew])>{{ $counts[$key] }}</p>
                    <p class="text-xs">{{ $label }}</p>
                </button>
            @endforeach
        </div>

        <p class="mb-3 text-sm text-hutan/60 dark:text-santan/60">Absen {{ substr($dormitory->check_window_start, 0, 5) }}–{{ substr($dormitory->check_window_end, 0, 5) }} · jam malam {{ substr($dormitory->curfew_time, 0, 5) }} WITA</p>

        <div class="space-y-4">
            @forelse ($groups as $roomNumber => $rows)
                <section wire:key="room-{{ $roomNumber }}">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Kamar {{ $roomNumber }}</h2>
                        @if ($rows->contains(fn ($r) => $r['attendance']?->status === \App\Enums\DormAttendanceStatus::PendingConfirmation) && $rows->first()['room'])
                            <flux:button size="xs" icon="check" wire:click="confirmRoom({{ $rows->first()['room']->id }})">Konfirmasi sekamar</flux:button>
                        @endif
                    </div>
                    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
                        @foreach ($rows as $row)
                            @php($attendance = $row['attendance'])
                            @php($missing = $row['status'] === null || $row['status'] === \App\Enums\DormAttendanceStatus::Rejected)
                            <div @class(['flex flex-wrap items-center gap-3 px-4 py-3', 'bg-rica-50 dark:bg-rica-700/20' => $missing && $pastCurfew]) wire:key="u-{{ $row['user']->id }}">
                                <flux:avatar size="sm" :name="$row['user']->name" :initials="$row['user']->initials()" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ $row['user']->name }}</p>
                                    <div class="flex flex-wrap items-center gap-1 text-xs text-hutan/60 dark:text-santan/60">
                                        @if ($row['status'])
                                            <x-status-badge :status="$row['status']" />
                                        @else
                                            <flux:badge size="sm" :color="$pastCurfew ? 'red' : 'zinc'">Belum absen</flux:badge>
                                        @endif
                                        @if ($attendance?->checked_in_at && $attendance->attempts > 0)
                                            <span>{{ jam($attendance->checked_in_at) }}</span>
                                            @if ($attendance->selfie_path)
                                                <a href="{{ $attendance->selfieUrl() }}" target="_blank" class="font-semibold text-woka underline">selfie</a>
                                            @endif
                                        @endif
                                        @if ($attendance?->status === \App\Enums\DormAttendanceStatus::Rejected)
                                            <span class="text-rica">{{ $attendance->reject_reason }}</span>
                                        @endif
                                        @if ($row['permit'])
                                            <span>· {{ $row['permit']->destination }}</span>
                                        @endif
                                        @if ($attendance?->confirmer)
                                            <span>· {{ $attendance->confirmer->name }}</span>
                                        @elseif ($attendance?->status === \App\Enums\DormAttendanceStatus::Confirmed && $attendance->confirmed_at)
                                            <span>· otomatis</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-1">
                                    @if ($attendance?->status === \App\Enums\DormAttendanceStatus::PendingConfirmation)
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="confirm({{ $attendance->id }})">Konfirmasi</flux:button>
                                    @elseif ($row['status'] !== \App\Enums\DormAttendanceStatus::Confirmed)
                                        <flux:button size="sm" icon="check" wire:click="present({{ $row['user']->id }})">Hadir</flux:button>
                                    @endif
                                    @if ($row['status'] !== \App\Enums\DormAttendanceStatus::NotPresent)
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="askNotPresent({{ $row['user']->id }})" aria-label="Tidak di asrama" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-card>
                </section>
            @empty
                <x-empty-state icon="home-modern" title="Tidak ada data" description="Belum ada penghuni pada filter ini." />
            @endforelse
        </div>
    @else
        <x-empty-state icon="home-modern" title="Belum ada asrama" description="Tambahkan asrama dan kamar di menu Asrama & Kamar." />
    @endif

    <flux:modal wire:model.self="showNote" class="w-full max-w-sm">
        <form wire:submit="notPresent" class="space-y-4">
            <flux:heading size="lg">Tidak di asrama</flux:heading>
            <flux:text>{{ $noteUser?->name }}</flux:text>
            <flux:textarea wire:model="note" label="Catatan" rows="2" placeholder="Belum kembali, sudah dihubungi via telepon" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
