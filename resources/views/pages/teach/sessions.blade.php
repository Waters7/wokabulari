<?php

use App\Actions\Sessions\ScheduleSessions;
use App\Enums\SessionStatus;
use App\Http\Requests\LearningSessionRequest;
use App\Models\Classroom;
use App\Models\LearningSession;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Sesi belajar')] class extends Component {
    #[Url]
    public string $week = '';

    #[Url]
    public ?int $classroom = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->week = $this->week ?: now()->startOfWeek()->toDateString();
        $this->resetForm();
    }

    protected function classrooms()
    {
        return Classroom::query()->active()->managedBy(auth()->user())->with('language')->orderBy('name')->get();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'classroom_id' => $this->classroom ?? $this->classrooms()->first()?->id,
            'date' => today()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'topic' => '',
            'objectives' => '',
            'repeat' => false,
            'weekdays' => [1, 2, 3, 4, 5],
            'weeks' => 4,
        ];
    }

    public function shift(int $weeks): void
    {
        $this->week = Carbon::parse($this->week)->addWeeks($weeks)->startOfWeek()->toDateString();
    }

    public function create(): void
    {
        $this->authorize('create', LearningSession::class);
        $this->resetValidation();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $session = LearningSession::findOrFail($id);
        $this->authorize('update', $session);
        $this->resetValidation();
        $this->editingId = $id;
        $this->form = [
            'classroom_id' => $session->classroom_id,
            'date' => $session->date->toDateString(),
            'start_time' => substr($session->start_time, 0, 5),
            'end_time' => substr($session->end_time, 0, 5),
            'topic' => $session->topic,
            'objectives' => $session->objectives,
            'repeat' => false,
            'weekdays' => [],
            'weeks' => 1,
        ];
        $this->showForm = true;
    }

    public function save(ScheduleSessions $schedule): void
    {
        $this->resetErrorBag();
        $data = $this->editingId ? collect($this->form)->except(['repeat', 'weekdays', 'weeks'])->all() : $this->form;
        $validator = Validator::make($data, LearningSessionRequest::rulesFor(! $this->editingId), [], ['classroom_id' => 'kelas', 'start_time' => 'jam mulai', 'end_time' => 'jam selesai', 'topic' => 'topik', 'weekdays' => 'hari', 'weeks' => 'jumlah minggu']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $classroom = $this->classrooms()->firstWhere('id', $this->form['classroom_id']);
        abort_unless($classroom, 403);

        if ($this->editingId) {
            $session = LearningSession::findOrFail($this->editingId);
            $this->authorize('update', $session);
            $session->update(collect($validator->validated())->only(['classroom_id', 'date', 'start_time', 'end_time', 'topic', 'objectives'])->all());
            Flux::toast(variant: 'success', text: 'Sesi diperbarui.');
        } else {
            $this->authorize('create', LearningSession::class);
            try {
                $created = $schedule->handle($classroom, $validator->validated(), auth()->id());
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['form.weekdays' => $e->getMessage()]);
            }
            Flux::toast(variant: 'success', text: $created->count().' sesi dijadwalkan. Mantap!');
            $this->week = Carbon::parse($this->form['date'])->startOfWeek()->toDateString();
        }

        $this->showForm = false;
    }

    public function cancel(int $id): void
    {
        $session = LearningSession::findOrFail($id);
        $this->authorize('update', $session);

        if ($session->status !== SessionStatus::Scheduled) {
            Flux::toast(variant: 'danger', text: 'Hanya sesi terjadwal yang bisa dibatalkan.');

            return;
        }

        $session->update(['status' => SessionStatus::Cancelled]);
        Flux::toast(text: 'Sesi dibatalkan.');
    }

    public function with(): array
    {
        $start = Carbon::parse($this->week)->startOfWeek();
        $end = $start->copy()->endOfWeek();
        $classrooms = $this->classrooms();

        $sessions = LearningSession::query()
            ->with(['classroom.language'])
            ->withCount(['attendances as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late'])])
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->when($this->classroom, fn ($q) => $q->where('classroom_id', $this->classroom))
            ->whereDateBetween('date', $start->toDateString(), $end->toDateString())
            ->orderBy('date')->orderBy('start_time')
            ->get()
            ->groupBy(fn ($s) => $s->date->toDateString());

        return [
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i)),
            'sessions' => $sessions,
            'classrooms' => $classrooms,
            'weekdayNames' => [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'],
        ];
    }
}; ?>

<div>
    <x-page-header title="Sesi belajar" subtitle="Jadwal sesi, kode kehadiran, materi, dan penilaian per sesi.">
        <x-slot:actions>
            @can('create', App\Models\LearningSession::class)
                <flux:button variant="primary" icon="plus" wire:click="create">Jadwalkan sesi</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Minggu sebelumnya" />
            <p class="font-display text-lg font-bold">{{ tanggal($start, 'j M') }} – {{ tanggal($start->copy()->endOfWeek()) }}</p>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Minggu berikutnya" />
        </div>
        <flux:select wire:model.live="classroom" class="sm:max-w-xs">
            <flux:select.option value="">Semua kelas saya</flux:select.option>
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="space-y-4">
        @foreach ($days as $day)
            @php($daySessions = $sessions[$day->toDateString()] ?? collect())
            @continue($daySessions->isEmpty() && $day->isWeekend())
            <section wire:key="day-{{ $day->toDateString() }}">
                <h2 @class(['mb-2 text-sm font-bold uppercase tracking-wide', 'text-woka' => $day->isToday(), 'text-hutan/60 dark:text-santan/60' => ! $day->isToday()])>
                    {{ tanggal($day, 'l, j M') }} @if ($day->isToday()) · Hari ini @endif
                </h2>
                @forelse ($daySessions as $session)
                    <x-card class="mb-2" wire:key="s-{{ $session->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <a href="{{ route('teach.sessions.show', $session) }}" wire:navigate class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-hutan/60 dark:text-santan/60">{{ $session->timeRange() }} · {{ $session->classroom->language->flag() }} {{ $session->classroom->label() }}</p>
                                <p class="font-display text-lg font-bold hover:text-woka">{{ $session->topic }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$session->status" />
                                    @if (in_array($session->status, [SessionStatus::Ongoing, SessionStatus::Completed], true))
                                        <span class="text-xs text-hutan/70">{{ $session->present_count }} hadir</span>
                                    @endif
                                </div>
                            </a>
                            <div class="flex shrink-0 items-center gap-1">
                                <flux:button size="sm" variant="primary" icon:trailing="arrow-right" :href="route('teach.sessions.show', $session)" wire:navigate>Buka</flux:button>
                                @if ($session->status === SessionStatus::Scheduled)
                                    <flux:dropdown align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Aksi" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="edit({{ $session->id }})">Ubah</flux:menu.item>
                                            <flux:menu.item icon="x-circle" variant="danger" wire:click="cancel({{ $session->id }})" wire:confirm="Batalkan sesi ini?">Batalkan</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </div>
                        </div>
                    </x-card>
                @empty
                    <p class="rounded-xl border border-dashed border-woka-100 px-4 py-3 text-sm text-hutan/50 dark:border-hutan-800">Tidak ada sesi.</p>
                @endforelse
            </section>
        @endforeach
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah sesi' : 'Jadwalkan sesi' }}</flux:heading>
            <flux:select wire:model="form.classroom_id" label="Kelas">
                @foreach ($classrooms as $c)
                    <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.date" type="date" :label="$form['repeat'] ? 'Mulai tanggal' : 'Tanggal'" />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.start_time" type="time" label="Jam mulai" />
                <flux:input wire:model="form.end_time" type="time" label="Jam selesai" />
            </div>
            <flux:input wire:model="form.topic" label="Topik" placeholder="Contoh: Percakapan di ruang perawatan" />
            <flux:textarea wire:model="form.objectives" label="Tujuan pembelajaran" rows="2" />

            @unless ($editingId)
                <flux:switch wire:model.live="form.repeat" label="Jadwal berulang" />
                @if ($form['repeat'])
                    <div class="space-y-3 rounded-xl bg-santan p-3 dark:bg-hutan-950">
                        <flux:checkbox.group wire:model="form.weekdays" label="Hari" variant="pills">
                            @foreach ($weekdayNames as $num => $name)
                                <flux:checkbox :value="$num" :label="$name" />
                            @endforeach
                        </flux:checkbox.group>
                        <flux:error name="form.weekdays" />
                        <flux:input wire:model="form.weeks" type="number" min="1" max="26" label="Selama (minggu)" />
                    </div>
                @endif
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
