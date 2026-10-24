<?php

use App\Actions\Attendance\ApproveDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Http\Requests\DailyAttendanceRequest;
use App\Models\Classroom;
use App\Models\DailyAttendance;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Absen harian peserta')] class extends Component {
    #[Url]
    public string $date = '';

    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $filter = '';

    public ?int $markUserId = null;

    public string $markStatus = 'present';

    public string $markNote = '';

    public ?int $detailId = null;

    public bool $showMark = false;

    public bool $showDetail = false;

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
        $this->classroom ??= $this->classrooms()->first()?->id;
    }

    protected function classrooms()
    {
        return Classroom::query()->active()->managedBy(auth()->user())->with('language')->orderBy('name')->get();
    }

    public function openMark(int $userId): void
    {
        $this->resetValidation();
        $this->markUserId = $userId;
        $this->markStatus = AttendanceStatus::Present->value;
        $this->markNote = '';
        $this->showMark = true;
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function mark(ApproveDailyAttendance $approve): void
    {
        $participant = User::query()->participants()->findOrFail($this->markUserId);
        $this->authorize('approve', [DailyAttendance::class, $participant]);

        $this->resetErrorBag();
        $validator = Validator::make(['status' => $this->markStatus, 'note' => $this->markNote], DailyAttendanceRequest::approvalRules(), [], ['note' => 'alasan']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['mark'.ucfirst($k) => $m])->all());
        }

        $approve->handle(auth()->user(), $participant, $this->date, AttendanceStatus::from($this->markStatus), $this->markNote);

        $this->showMark = false;
        Flux::toast(variant: 'success', text: "Absen {$participant->name} ditandai.");
    }

    public function with(): array
    {
        $classrooms = $this->classrooms();
        $classroom = $classrooms->firstWhere('id', $this->classroom);

        $participants = $classroom
            ? $classroom->participants()->with('profile')->orderBy('name')->get()
            : collect();

        $records = DailyAttendance::query()
            ->whereIn('user_id', $participants->pluck('id'))
            ->whereDate('date', $this->date)
            ->with('approver')
            ->get()
            ->keyBy('user_id');

        $rows = $participants->map(fn ($p) => ['user' => $p, 'record' => $records->get($p->id)])
            ->filter(fn ($row) => match ($this->filter) {
                'missing' => $row['record'] === null,
                'rejected' => $row['record']?->status === AttendanceStatus::Rejected,
                'late' => $row['record']?->status === AttendanceStatus::Late,
                default => true,
            });

        return [
            'classrooms' => $classrooms,
            'rows' => $rows,
            'summary' => [
                'total' => $participants->count(),
                'present' => $records->filter(fn ($r) => $r->status->countsAsAttended())->count(),
                'rejected' => $records->where('status', AttendanceStatus::Rejected)->count(),
                'missing' => $participants->count() - $records->count(),
            ],
            'detail' => $this->detailId ? DailyAttendance::with('user')->find($this->detailId) : null,
            'statuses' => AttendanceStatus::manualOptions(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Absen harian peserta" subtitle="Pantau absen wajah + lokasi, tinjau yang ditolak, dan tandai manual dengan alasan." />

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:input type="date" wire:model.live="date" />
        <flux:select wire:model.live="classroom">
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="filter">
            <flux:select.option value="">Semua peserta</flux:select.option>
            <flux:select.option value="missing">Belum absen</flux:select.option>
            <flux:select.option value="rejected">Ditolak</flux:select.option>
            <flux:select.option value="late">Terlambat</flux:select.option>
        </flux:select>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat label="Peserta" :value="$summary['total']" icon="user-group" />
        <x-stat label="Hadir" :value="$summary['present']" icon="check-circle" />
        <x-stat label="Ditolak" :value="$summary['rejected']" icon="x-circle" tone="rica" />
        <x-stat label="Belum absen" :value="$summary['missing']" icon="clock" tone="kunyit" />
    </div>

    @if ($classrooms->isEmpty())
        <x-empty-state icon="building-library" title="Tidak ada kelas aktif" />
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Peserta</th><th>Jam</th><th>Status</th><th>Jarak</th><th>Wajah</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($record = $row['record'])
                        <tr wire:key="r-{{ $row['user']->id }}">
                            <td class="font-semibold">{{ $row['user']->name }}</td>
                            <td>{{ $record?->checked_in_at ? jam($record->checked_in_at) : '–' }}</td>
                            <td>
                                @if ($record)
                                    <x-status-badge :status="$record->status" />
                                    @if ($record->status === AttendanceStatus::Rejected)
                                        <span class="block max-w-56 text-xs text-rica">{{ $record->reject_reason }}</span>
                                    @elseif ($record->approval_note)
                                        <span class="block max-w-56 text-xs text-hutan/60">Manual: {{ $record->approval_note }} ({{ $record->approver?->name }})</span>
                                    @endif
                                @else
                                    <flux:badge size="sm" color="zinc">Belum absen</flux:badge>
                                @endif
                            </td>
                            <td>{{ $record?->distance_m !== null ? angka($record->distance_m).' m' : '–' }}</td>
                            <td>{{ $record?->face_distance !== null ? angka($record->face_distance, 2) : '–' }}</td>
                            <td class="whitespace-nowrap text-end">
                                @if ($record?->selfie_path)
                                    <flux:button size="sm" variant="ghost" icon="photo" wire:click="openDetail({{ $record->id }})" aria-label="Lihat selfie" />
                                @endif
                                @if (! $record || ! $record->isAttended())
                                    <flux:button size="sm" icon="pencil-square" wire:click="openMark({{ $row['user']->id }})">Tandai</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Tidak ada data untuk filter ini" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <flux:modal wire:model.self="showMark" class="w-full max-w-md">
        <form wire:submit="mark" class="space-y-4">
            <flux:heading size="lg">Tandai absen manual</flux:heading>
            <flux:text>Tanggal {{ tanggal_panjang($date) }}. Penandaan manual tercatat di log audit.</flux:text>
            <flux:select wire:model="markStatus" label="Status">
                @foreach ($statuses as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="markNote" label="Alasan" rows="2" placeholder="Contoh: kamera HP rusak, sudah dicek langsung di kelas" />
            <flux:error name="markNote" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDetail" class="w-full max-w-sm">
        @if ($detail)
            <flux:heading size="lg">{{ $detail->user->name }}</flux:heading>
            <img src="{{ $detail->selfieUrl() }}" alt="Selfie absen" class="mt-3 w-full rounded-xl">
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt>Jam</dt><dd>{{ jam($detail->checked_in_at) }} WITA</dd></div>
                <div class="flex justify-between"><dt>Akurasi GPS</dt><dd>{{ $detail->accuracy_m !== null ? angka($detail->accuracy_m).' m' : '–' }}</dd></div>
                <div class="flex justify-between"><dt>Jarak ke kelas</dt><dd>{{ $detail->distance_m !== null ? angka($detail->distance_m).' m' : '–' }}</dd></div>
                <div class="flex justify-between"><dt>Skor wajah</dt><dd>{{ $detail->face_distance !== null ? angka($detail->face_distance, 3) : '–' }}</dd></div>
                <div class="flex justify-between"><dt>Percobaan</dt><dd>{{ $detail->attempts }}×</dd></div>
            </dl>
        @endif
    </flux:modal>
</div>
