<?php

use App\Actions\Sessions\ManageSessionCode;
use App\Actions\Sessions\MarkSessionAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\DailyAttendance;
use App\Models\LeaveRequest;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\QrCodeService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public LearningSession $session;

    public ?int $markUserId = null;

    public string $markStatus = 'excused';

    public string $markNote = '';

    public bool $showMark = false;

    #[On('session-updated')]
    public function refreshSession(): void
    {
        $this->session->refresh();
    }

    public function newCode(ManageSessionCode $codes): void
    {
        $this->authorize('update', $this->session);
        $this->run(fn () => $codes->generate($this->session, auth()->user()), 'Kode baru dibuat. Kode lama tidak berlaku lagi.');
    }

    public function extend(ManageSessionCode $codes): void
    {
        $this->authorize('update', $this->session);
        $this->run(fn () => $codes->extend($this->session, 5), 'Kode diperpanjang 5 menit.');
    }

    public function closeCode(ManageSessionCode $codes): void
    {
        $this->authorize('update', $this->session);
        $codes->close($this->session);
        Flux::toast(text: 'Kode ditutup.');
    }

    protected function run(callable $action, string $message): void
    {
        try {
            $action();
            $this->session->unsetRelation('activeCode');
            Flux::toast(variant: 'success', text: $message);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openMark(int $userId): void
    {
        $this->authorize('update', $this->session);
        $this->resetErrorBag();
        $this->markUserId = $userId;
        $this->markStatus = AttendanceStatus::Excused->value;
        $this->markNote = '';
        $this->showMark = true;
    }

    public function mark(MarkSessionAttendance $mark): void
    {
        $this->authorize('update', $this->session);
        $this->resetErrorBag();
        $this->validate(['markStatus' => ['required', 'in:present,late,excused,sick,absent'], 'markNote' => ['required', 'string', 'min:3', 'max:255']], [], ['markNote' => 'alasan']);

        $participant = User::findOrFail($this->markUserId);

        try {
            $mark->handle($this->session, $participant, AttendanceStatus::from($this->markStatus), $this->markNote, auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('markNote', $e->getMessage());

            return;
        }

        $this->showMark = false;
        Flux::toast(variant: 'success', text: "Kehadiran {$participant->name} ditandai.");
    }

    public function with(): array
    {
        $qr = app(QrCodeService::class);
        $code = $this->session->isOngoing() ? $this->session->activeCode()->first() : null;
        $participants = $this->session->classroom->participants()->orderBy('name')->get();
        $records = $this->session->attendances()->with('marker')->get()->keyBy('user_id');
        $daily = DailyAttendance::query()->whereIn('user_id', $participants->pluck('id'))->whereDate('date', $this->session->date)->get()->keyBy('user_id');
        $leaves = LeaveRequest::query()->approved()->covering($this->session->date)->whereIn('user_id', $participants->pluck('id'))->get()->keyBy('user_id');

        return [
            'code' => $code,
            'qr' => $code ? $qr->svg(route('sessions.code', ['kode' => $code->code]), 220) : null,
            'participants' => $participants,
            'records' => $records,
            'daily' => $daily,
            'leaves' => $leaves,
            'submitted' => $records->filter(fn ($r) => $r->status->countsAsAttended())->count(),
            'canManage' => auth()->user()->can('update', $this->session),
            'statuses' => AttendanceStatus::manualOptions(),
        ];
    }
}; ?>

<div @if ($session->isOngoing()) wire:poll.5s @endif>
    @if ($session->isOngoing())
        <div class="mb-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
            <x-card class="text-center">
                @if ($code)
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-hutan/60 dark:text-santan/60">Kode kehadiran</p>
                    <p class="my-2 select-all font-mono text-5xl font-extrabold tracking-[0.25em] text-woka sm:text-6xl dark:text-woka-light">{{ $code->code }}</p>
                    <div class="mx-auto w-48 rounded-xl bg-white p-2 [&>svg]:h-auto [&>svg]:w-full">{!! $qr !!}</div>
                    <p class="mt-2 text-sm" x-data="countdown('{{ $code->expires_at->toIso8601String() }}')" wire:key="cd-{{ $code->id }}-{{ $code->expires_at->timestamp }}">
                        Berlaku <span class="font-mono font-bold" x-text="display"></span> lagi
                    </p>
                    @if ($canManage)
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <flux:button size="sm" icon="plus" wire:click="extend">+5 menit</flux:button>
                            <flux:button size="sm" icon="arrow-path" wire:click="newCode">Kode baru</flux:button>
                            <flux:button size="sm" icon="lock-closed" wire:click="closeCode">Tutup kode</flux:button>
                            <flux:button size="sm" variant="primary" icon="tv" :href="route('teach.sessions.projector', $session)" target="_blank">Proyektor</flux:button>
                        </div>
                    @endif
                @else
                    <flux:icon.lock-closed class="mx-auto size-10 text-hutan/40" />
                    <p class="mt-2 font-semibold">Belum ada kode aktif</p>
                    <p class="text-sm text-hutan/60 dark:text-santan/60">Kode kedaluwarsa atau ditutup.</p>
                    @if ($canManage)
                        <flux:button class="mt-3" variant="primary" icon="arrow-path" wire:click="newCode">Buat kode baru</flux:button>
                    @endif
                @endif
            </x-card>

            <div class="grid grid-cols-2 gap-3 self-start">
                <x-stat label="Sudah input" :value="$submitted.' / '.$participants->count()" icon="check-circle" />
                <x-stat label="Belum" :value="$participants->count() - $records->count()" icon="clock" tone="kunyit" />
                <x-card class="col-span-2 text-sm">
                    <p class="flex items-center gap-2"><span class="inline-block size-2 animate-pulse rounded-full bg-woka-light"></span> Monitor diperbarui otomatis tiap 5 detik.</p>
                    <p class="mt-1 text-hutan/60 dark:text-santan/60">Peserta harus sudah absen harian sebelum input kode.</p>
                </x-card>
            </div>
        </div>
    @elseif ($session->status === SessionStatus::Scheduled)
        <flux:callout icon="information-circle" class="mb-4" text="Tekan Mulai sesi untuk membuat kode kehadiran dan QR." />
    @endif

    <div class="table-scroll">
        <table>
            <thead><tr><th>Peserta</th><th>Absen harian</th><th>Sesi</th><th>Waktu</th><th></th></tr></thead>
            <tbody>
                @foreach ($participants as $participant)
                    @php($record = $records->get($participant->id))
                    <tr wire:key="sa-{{ $participant->id }}">
                        <td class="font-semibold">
                            {{ $participant->name }}
                            @if ($leave = $leaves->get($participant->id))
                                <span class="block text-xs font-normal text-sky-700">{{ $leave->type->label() }} disetujui</span>
                            @endif
                        </td>
                        <td>
                            @if ($d = $daily->get($participant->id))
                                <x-status-badge :status="$d->status" />
                            @else
                                <flux:badge size="sm" color="zinc">Belum</flux:badge>
                            @endif
                        </td>
                        <td>
                            @if ($record)
                                <x-status-badge :status="$record->status" />
                                @if ($record->note)
                                    <span class="block max-w-52 truncate text-xs text-hutan/60" title="{{ $record->note }}">{{ $record->note }}</span>
                                @endif
                            @else
                                <flux:badge size="sm" color="zinc">Belum input</flux:badge>
                            @endif
                        </td>
                        <td>{{ $record?->submitted_at ? jam($record->submitted_at) : '–' }}</td>
                        <td class="text-end">
                            @if ($canManage && $session->status !== SessionStatus::Scheduled)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openMark({{ $participant->id }})">Tandai</flux:button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <flux:modal wire:model.self="showMark" class="w-full max-w-md">
        <form wire:submit="mark" class="space-y-4">
            <flux:heading size="lg">Tandai kehadiran sesi</flux:heading>
            <flux:select wire:model="markStatus" label="Status">
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="markNote" label="Alasan" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
