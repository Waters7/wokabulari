<?php

use App\Actions\Health\ReportSickness;
use App\Enums\HealthSeverity;
use App\Http\Requests\HealthReportRequest;
use App\Models\HealthReport;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Lapor sakit')] class extends Component {
    use WithFileUploads;

    public bool $showForm = false;

    /** ID peserta yang dilaporkan (diri sendiri atau teman sekamar). */
    public ?int $forUserId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public $photo = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->forUserId = auth()->id();
        $this->form = [
            'symptoms' => [], 'description' => '', 'started_at' => '', 'temperature' => '',
            'severity' => HealthSeverity::Mild->value, 'needs_special_meal' => false, 'special_meal_note' => '', 'deliver_to_room' => true,
        ];
        $this->photo = null;
    }

    public function create(): void
    {
        $this->authorize('create', HealthReport::class);
        $this->resetErrorBag();
        $this->resetForm();
        $this->showForm = true;
    }

    protected function target(): User
    {
        return $this->roommates()->push(auth()->user())->firstWhere('id', $this->forUserId) ?? auth()->user();
    }

    protected function roommates()
    {
        $roomId = auth()->user()->currentRoomAssignment?->dorm_room_id;

        return $roomId
            ? User::query()->whereKeyNot(auth()->id())->active()->whereHas('currentRoomAssignment', fn ($q) => $q->where('dorm_room_id', $roomId))->orderBy('name')->get()
            : collect();
    }

    public function save(ReportSickness $report): void
    {
        $this->authorize('create', HealthReport::class);
        $this->resetErrorBag();

        $validator = Validator::make(array_merge($this->form, ['photo' => $this->photo]), HealthReportRequest::rulesFor(), [
            'symptoms.required' => 'Pilih minimal satu gejala.',
        ], ['severity' => 'tingkat keparahan', 'temperature' => 'suhu', 'started_at' => 'sejak kapan', 'photo' => 'foto']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'photo' ? 'photo' : 'form.'.explode('.', $k)[0] => $m])->all());
        }

        try {
            $report->handle($this->target(), auth()->user(), $validator->validated(), $this->photo);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.severity', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Laporan terkirim. Petugas asrama & kesehatan so dapa kabar. Cepat sembuh, neh!');
    }

    public function emergency(ReportSickness $report): void
    {
        $this->authorize('create', HealthReport::class);
        $report->emergency(auth()->user(), auth()->user());
        Flux::toast(variant: 'danger', text: 'Sinyal DARURAT terkirim ke semua petugas. Tetap tenang, bantuan segera datang.', duration: 8000);
    }

    public function with(): array
    {
        return [
            'reports' => HealthReport::query()->where('user_id', auth()->id())->with(['actions.handler', 'reporter'])->latest('reported_at')->limit(10)->get(),
            'symptoms' => HealthReport::SYMPTOMS,
            'severities' => HealthSeverity::cases(),
            'roommates' => $this->roommates(),
            'dormContact' => setting('program.dorm_contact'),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Lapor sakit" subtitle="Rasa tidak enak badan? Lapor supaya petugas asrama & kesehatan bisa bantu." />

    {{-- Tombol darurat: konfirmasi dulu agar tidak tertekan tidak sengaja --}}
    <div x-data="{ confirm: false }" class="mb-5">
        <button type="button" x-show="!confirm" x-on:click="confirm = true"
                class="flex w-full items-center justify-center gap-3 rounded-3xl bg-rica px-6 py-5 font-display text-2xl font-bold text-white shadow-lg transition active:scale-95">
            <flux:icon.exclamation-triangle class="size-8" /> DARURAT
        </button>
        <div x-show="confirm" x-cloak class="rounded-3xl bg-rica-50 p-4 text-center ring-2 ring-rica dark:bg-hutan-900">
            <p class="font-semibold text-rica-700 dark:text-rica-100">Kirim sinyal darurat ke semua petugas sekarang?</p>
            <div class="mt-3 flex gap-2">
                <flux:button class="flex-1" x-on:click="confirm = false">Batal</flux:button>
                <flux:button class="flex-1" variant="danger" icon="bell-alert" x-on:click="confirm = false; $wire.emergency()">Ya, kirim</flux:button>
            </div>
        </div>
        <p class="mt-2 text-center text-xs text-hutan/60 dark:text-santan/60">Untuk kondisi gawat (sesak berat, pingsan, kejang, perdarahan).@if ($dormContact) Kontak asrama: <a href="tel:{{ $dormContact }}" class="font-semibold underline">{{ $dormContact }}</a>@endif</p>
    </div>

    <flux:button variant="primary" icon="heart" class="mb-5 w-full" wire:click="create">Lapor sakit / tidak enak badan</flux:button>

    @if ($reports->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Laporan saya</h2>
        <div class="space-y-3">
            @foreach ($reports as $report)
                <x-card wire:key="hr-{{ $report->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ implode(', ', $report->symptomLabels()) ?: 'Sinyal darurat' }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal_jam($report->reported_at) }}@unless ($report->isSelfReported()) · dilaporkan {{ $report->reporter?->name }}@endunless</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <x-status-badge :status="$report->status" />
                            <x-status-badge :status="$report->severity" />
                        </div>
                    </div>
                    @if ($report->actions->isNotEmpty())
                        <ol class="mt-3 space-y-2 border-s-2 border-woka-100 ps-4 dark:border-hutan-800">
                            @foreach ($report->actions as $action)
                                <li class="text-sm">
                                    <p class="font-semibold">{{ $action->action_type->label() }} <span class="font-normal text-hutan/60 dark:text-santan/60">· {{ tanggal_jam($action->acted_at) }}</span></p>
                                    @if ($action->medicine)<p>{{ $action->medicine }} {{ $action->dosage }}</p>@endif
                                    @if ($action->referral_place)<p>Rujukan: {{ $action->referral_place }}</p>@endif
                                    @if ($action->condition_note)<p class="text-hutan/70 dark:text-santan/70">{{ $action->condition_note }}</p>@endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Lapor sakit</flux:heading>
            @if ($roommates->isNotEmpty())
                <flux:select wire:model="forUserId" label="Untuk siapa?">
                    <flux:select.option :value="auth()->id()">Diri sendiri</flux:select.option>
                    @foreach ($roommates as $mate)
                        <flux:select.option :value="$mate->id">{{ $mate->name }} (teman sekamar)</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:checkbox.group wire:model="form.symptoms" label="Gejala" class="grid grid-cols-2 gap-2">
                @foreach ($symptoms as $key => $label)
                    <flux:checkbox :value="$key" :label="$label" />
                @endforeach
            </flux:checkbox.group>
            <flux:error name="form.symptoms" />
            <flux:radio.group wire:model="form.severity" label="Seberapa parah?" variant="segmented" size="sm">
                @foreach ($severities as $severity)
                    <flux:radio :value="$severity->value" :label="$severity->label()" />
                @endforeach
            </flux:radio.group>
            <flux:textarea wire:model="form.description" label="Ceritakan keluhannya" rows="2" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.started_at" type="datetime-local" label="Sejak kapan" />
                <flux:input wire:model="form.temperature" type="number" step="0.1" label="Suhu (°C)" placeholder="37.5" />
            </div>
            <flux:switch wire:model.live="form.needs_special_meal" label="Butuh makanan khusus / diantar ke kamar" />
            @if ($form['needs_special_meal'] ?? false)
                <flux:input wire:model="form.special_meal_note" placeholder="Bubur, tanpa pedas" />
            @endif
            <flux:input type="file" wire:model="photo" label="Foto (opsional)" accept="image/*" />
            <p class="text-xs text-hutan/60 dark:text-santan/60">Data kesehatan hanya dilihat petugas asrama/kesehatan dan walimu.</p>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim laporan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
