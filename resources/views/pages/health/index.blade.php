<?php

use App\Actions\Health\ReportSickness;
use App\Enums\HealthReportStatus;
use App\Enums\HealthSeverity;
use App\Enums\Role;
use App\Http\Requests\HealthReportRequest;
use App\Models\HealthReport;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Laporan sakit')] class extends Component {
    #[Url]
    public string $tab = 'open';

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('viewAny', HealthReport::class);
    }

    /** Petugas melapor atas nama peserta. */
    public function create(): void
    {
        $this->authorize('handle', HealthReport::class);
        $this->resetErrorBag();
        $this->form = ['user_id' => null, 'symptoms' => [], 'description' => '', 'temperature' => '', 'severity' => HealthSeverity::Mild->value, 'needs_special_meal' => false, 'special_meal_note' => '', 'deliver_to_room' => true];
        $this->showForm = true;
    }

    public function save(ReportSickness $report): void
    {
        $this->authorize('handle', HealthReport::class);
        $this->resetErrorBag();

        $validator = Validator::make($this->form, HealthReportRequest::rulesFor() + ['user_id' => ['required', 'exists:users,id']], ['symptoms.required' => 'Pilih minimal satu gejala.'], ['user_id' => 'peserta', 'severity' => 'tingkat keparahan', 'temperature' => 'suhu']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.explode('.', $k)[0] => $m])->all());
        }

        try {
            $created = $report->handle(User::query()->findOrFail($this->form['user_id']), auth()->user(), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.user_id', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->redirectRoute('health.show', $created, navigate: true);
    }

    public function with(): array
    {
        $query = HealthReport::query()->with(['user', 'dormitory', 'room'])->withCount('actions');

        return [
            'reports' => $query->when($this->tab === 'open', fn ($q) => $q->open())
                ->when($this->tab === 'recovered', fn ($q) => $q->where('status', HealthReportStatus::Recovered))
                ->orderByDesc('is_emergency')
                ->orderByRaw("case severity when 'emergency' then 0 when 'severe' then 1 when 'moderate' then 2 else 3 end")
                ->latest('reported_at')->limit(60)->get(),
            'stats' => [
                'open' => HealthReport::query()->open()->count(),
                'emergency' => HealthReport::query()->open()->where('is_emergency', true)->count(),
                'referred' => HealthReport::query()->where('status', HealthReportStatus::Referred)->count(),
                'today' => HealthReport::query()->whereDate('reported_at', today())->count(),
            ],
            'participants' => $this->showForm ? User::query()->role(Role::Participant->value)->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'symptoms' => HealthReport::SYMPTOMS,
            'severities' => HealthSeverity::cases(),
            'canHandle' => auth()->user()->can('handle', HealthReport::class),
        ];
    }
}; ?>

<div wire:poll.15s>
    <x-page-header title="Laporan sakit" subtitle="Laporan dari peserta beserta status penanganan. Data medis bersifat rahasia.">
        @if ($canHandle)
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="create">Lapor atas nama peserta</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <x-stat label="Ditangani" :value="$stats['open']" icon="heart" />
        <x-stat label="Darurat" :value="$stats['emergency']" icon="exclamation-triangle" :tone="$stats['emergency'] ? 'rica' : 'woka'" />
        <x-stat label="Dirujuk" :value="$stats['referred']" icon="building-office-2" tone="kunyit" />
        <x-stat label="Hari ini" :value="$stats['today']" icon="calendar" />
    </div>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="mb-4">
        <flux:radio value="open" :label="'Terbuka'" />
        <flux:radio value="recovered" :label="'Pulih'" />
        <flux:radio value="all" :label="'Semua'" />
    </flux:radio.group>

    <div class="space-y-3">
        @forelse ($reports as $report)
            <x-card :href="route('health.show', $report)" wire:key="hi-{{ $report->id }}" class="{{ $report->is_emergency && $report->status->isOpen() ? 'ring-2! ring-rica!' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">@if ($report->is_emergency)<span class="text-rica">DARURAT · </span>@endif{{ $report->user->name }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $report->dormitory?->name }}@if ($report->room) · kamar {{ $report->room->number }}@endif · {{ tanggal_jam($report->reported_at) }}</p>
                        <p class="mt-1 truncate text-sm">{{ implode(', ', $report->symptomLabels()) ?: 'Tombol darurat' }}@if ($report->temperature) · {{ $report->temperature }}°C @endif</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $report->actions_count }} tindakan</p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-status-badge :status="$report->status" />
                        <x-status-badge :status="$report->severity" />
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="heart" title="Tidak ada laporan" description="Semua peserta sehat. Puji Tuhan!" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Lapor atas nama peserta</flux:heading>
            <flux:select wire:model="form.user_id" label="Peserta" placeholder="Pilih peserta">
                @foreach ($participants as $p)
                    <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox.group wire:model="form.symptoms" label="Gejala" class="grid grid-cols-2 gap-2">
                @foreach ($symptoms as $key => $label)
                    <flux:checkbox :value="$key" :label="$label" />
                @endforeach
            </flux:checkbox.group>
            <flux:error name="form.symptoms" />
            <flux:radio.group wire:model="form.severity" label="Tingkat keparahan" variant="segmented" size="sm">
                @foreach ($severities as $severity)
                    <flux:radio :value="$severity->value" :label="$severity->label()" />
                @endforeach
            </flux:radio.group>
            <flux:textarea wire:model="form.description" label="Keterangan" rows="2" />
            <flux:input wire:model="form.temperature" type="number" step="0.1" label="Suhu (°C)" />
            <flux:switch wire:model.live="form.needs_special_meal" label="Butuh makanan khusus / diantar" />
            @if ($form['needs_special_meal'] ?? false)
                <flux:input wire:model="form.special_meal_note" placeholder="Bubur, tanpa pedas" />
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
