<?php

use App\Actions\Health\HandleHealthReport;
use App\Enums\HealthActionType;
use App\Enums\MealType;
use App\Enums\ReviewStatus;
use App\Http\Requests\HealthReportRequest;
use App\Models\HealthReport;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail laporan sakit')] class extends Component {
    #[Locked]
    public int $reportId;

    /** @var array<string, mixed> */
    public array $action = [];

    public string $mealRequest = '';

    public string $mealType = '';

    public bool $mealToRoom = true;

    public function mount(HealthReport $report): void
    {
        $this->authorize('view', $report);
        $this->reportId = $report->id;
        $this->resetAction();
    }

    protected function report(): HealthReport
    {
        return HealthReport::query()->findOrFail($this->reportId);
    }

    protected function resetAction(): void
    {
        $this->action = ['action_type' => HealthActionType::Examination->value, 'medicine' => '', 'dosage' => '', 'referral_place' => '', 'condition_note' => '', 'next_check_at' => ''];
    }

    public function addAction(HandleHealthReport $handle): void
    {
        $report = $this->report();
        $this->authorize('handle', $report);
        $this->resetErrorBag();

        $validator = Validator::make($this->action, HealthReportRequest::actionRules(), [], ['action_type' => 'tindakan', 'next_check_at' => 'jadwal pantau berikutnya']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['action.'.$k => $m])->all());
        }

        try {
            $handle->addAction($report, auth()->user(), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            $this->addError('action.action_type', $e->getMessage());

            return;
        }

        $this->resetAction();
        Flux::toast(variant: 'success', text: 'Tindakan dicatat.');
    }

    public function sickLeave(bool $approve, HandleHealthReport $handle): void
    {
        $report = $this->report();
        $this->authorize('handle', $report);

        try {
            $handle->confirmSickLeave($report, auth()->user(), $approve, $approve ? null : 'Tidak perlu izin kelas menurut petugas');
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: $approve ? 'Izin sakit kelas dikonfirmasi.' : 'Izin sakit kelas ditolak.');
    }

    public function requestMeal(HandleHealthReport $handle): void
    {
        $report = $this->report();
        $this->authorize('handle', $report);
        $this->resetErrorBag();

        if (mb_strlen(trim($this->mealRequest)) < 3) {
            $this->addError('mealRequest', 'Tulis kebutuhan makanannya.');

            return;
        }

        $handle->requestSpecialMeal($report, auth()->user(), $this->mealRequest, $this->mealToRoom, MealType::tryFrom($this->mealType)?->value);
        $this->mealRequest = '';
        Flux::toast(variant: 'success', text: 'Permintaan dikirim ke unit konsumsi.');
    }

    public function with(): array
    {
        $report = HealthReport::query()->with(['user.profile', 'user.allGuardians', 'reporter', 'dormitory', 'room', 'actions.handler', 'sickLeave', 'specialMeals'])->findOrFail($this->reportId);

        return [
            'report' => $report,
            'profile' => $report->user->profile,
            'canHandle' => auth()->user()->can('handle', $report),
            'actionTypes' => HealthActionType::cases(),
            'mealTypes' => MealType::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header :title="$report->user->name" :subtitle="'Laporan '.tanggal_jam($report->reported_at).' WITA'" :back="auth()->user()->can('viewAny', App\Models\HealthReport::class) ? route('health.index') : null" />

    @if ($report->is_emergency && $report->status->isOpen())
        <flux:callout icon="exclamation-triangle" color="red" class="mb-4" heading="DARURAT" text="Segera datangi peserta. Gunakan tombol telepon di bawah." />
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="min-w-0 space-y-4 lg:col-span-2">
            <x-card>
                <div class="flex flex-wrap gap-2">
                    <x-status-badge :status="$report->status" size="lg" />
                    <x-status-badge :status="$report->severity" size="lg" />
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Gejala</dt><dd class="font-semibold">{{ implode(', ', $report->symptomLabels()) ?: '-' }}</dd></div>
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Suhu</dt><dd class="font-semibold">{{ $report->temperature ? $report->temperature.' °C' : '-' }}</dd></div>
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Sejak</dt><dd>{{ $report->started_at ? tanggal_jam($report->started_at) : '-' }}</dd></div>
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Dilaporkan oleh</dt><dd>{{ $report->isSelfReported() ? 'Peserta sendiri' : $report->reporter?->name }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-hutan/60 dark:text-santan/60">Keterangan</dt><dd>{{ $report->description ?: '-' }}</dd></div>
                </dl>
                @if ($report->photo_path)
                    <a href="{{ $report->photoUrl() }}" target="_blank" class="mt-3 inline-block text-sm font-semibold text-woka underline">Lihat foto</a>
                @endif
            </x-card>

            <x-card title="Timeline penanganan">
                @if ($report->actions->isEmpty())
                    <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada tindakan.</p>
                @else
                    <ol class="space-y-3 border-s-2 border-woka-100 ps-4 dark:border-hutan-800">
                        @foreach ($report->actions as $act)
                            <li wire:key="act-{{ $act->id }}">
                                <p class="font-semibold">{{ $act->action_type->label() }} <span class="text-sm font-normal text-hutan/60 dark:text-santan/60">· {{ tanggal_jam($act->acted_at) }} · {{ $act->handler?->name }}</span></p>
                                @if ($act->medicine)<p class="text-sm">Obat: {{ $act->medicine }} {{ $act->dosage }}</p>@endif
                                @if ($act->referral_place)<p class="text-sm">Rujukan: {{ $act->referral_place }}</p>@endif
                                @if ($act->condition_note)<p class="text-sm text-hutan/70 dark:text-santan/70">{{ $act->condition_note }}</p>@endif
                                @if ($act->next_check_at)<p class="text-xs text-kunyit-700">Pantau lagi {{ tanggal_jam($act->next_check_at) }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if ($canHandle)
                    <form wire:submit="addAction" class="mt-5 space-y-3 border-t border-woka-100 pt-4 dark:border-hutan-800">
                        <flux:select wire:model.live="action.action_type" label="Tindakan">
                            @foreach ($actionTypes as $type)
                                <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if (($action['action_type'] ?? '') === 'medication')
                            <div class="grid grid-cols-2 gap-3">
                                <flux:input wire:model="action.medicine" label="Obat" placeholder="Paracetamol 500 mg" />
                                <flux:input wire:model="action.dosage" label="Dosis" placeholder="3×1 sesudah makan" />
                            </div>
                        @endif
                        @if (($action['action_type'] ?? '') === 'referral')
                            <flux:input wire:model="action.referral_place" label="Tempat rujukan" placeholder="RSUP Prof. Kandou" />
                        @endif
                        <flux:textarea wire:model="action.condition_note" label="Catatan kondisi" rows="2" />
                        <flux:input wire:model="action.next_check_at" type="datetime-local" label="Jadwal pantau berikutnya (opsional)" />
                        <flux:button type="submit" variant="primary" icon="plus">Catat tindakan</flux:button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="min-w-0 space-y-4">
            <x-card title="Peserta">
                <p class="text-sm">{{ $report->dormitory?->name ?? 'Asrama ?' }}@if ($report->room) · Kamar {{ $report->room->number }}@endif</p>
                <div class="mt-3 grid gap-2">
                    @if ($report->user->phone)
                        <flux:button icon="phone" :href="'tel:+'.$report->user->phone">Telepon peserta</flux:button>
                    @endif
                    @if ($profile?->emergency_contact_phone)
                        <flux:button icon="phone" :href="'tel:'.$profile->emergency_contact_phone">Kontak darurat: {{ $profile->emergency_contact_name }}</flux:button>
                    @endif
                    @foreach ($report->user->allGuardians as $guardian)
                        @if ($guardian->phone)
                            <flux:button icon="phone" variant="ghost" :href="'tel:+'.$guardian->phone">Wali: {{ $guardian->name }}</flux:button>
                        @endif
                    @endforeach
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Alergi</dt><dd @class(['font-semibold', 'text-rica' => $profile?->hasAllergies()])>{{ $profile?->allergies ?: '-' }}</dd></div>
                    <div><dt class="text-xs text-hutan/60 dark:text-santan/60">Riwayat penyakit</dt><dd>{{ $profile?->medical_history ?: '-' }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Izin sakit kelas">
                @if ($leave = $report->sickLeave)
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm">{{ $leave->periodLabel() }}</p>
                        <x-status-badge :status="$leave->status" />
                    </div>
                    @if ($canHandle && $leave->status === ReviewStatus::Pending)
                        <div class="mt-3 flex gap-2">
                            <flux:button size="sm" variant="primary" icon="check" wire:click="sickLeave(true)">Konfirmasi</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="sickLeave(false)">Tidak perlu</flux:button>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-hutan/60 dark:text-santan/60">Sudah ada izin sakit untuk hari ini atau tidak dibuat.</p>
                @endif
            </x-card>

            <x-card title="Makanan khusus">
                @foreach ($report->specialMeals as $meal)
                    <div class="flex items-center justify-between gap-2 py-1 text-sm" wire:key="sm-{{ $meal->id }}">
                        <span class="min-w-0">{{ $meal->request }}@if ($meal->delivered_to_room) · antar kamar @endif</span>
                        <x-status-badge :status="$meal->status" />
                    </div>
                @endforeach
                @if ($canHandle)
                    <form wire:submit="requestMeal" class="mt-3 space-y-2">
                        <flux:input wire:model="mealRequest" placeholder="Bubur, tanpa pedas" />
                        <div class="flex items-center gap-2">
                            <flux:select wire:model="mealType" size="sm" class="flex-1">
                                <flux:select.option value="">Sepanjang hari</flux:select.option>
                                @foreach ($mealTypes as $type)
                                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:checkbox wire:model="mealToRoom" label="Antar" />
                        </div>
                        <flux:button type="submit" size="sm" icon="paper-airplane">Kirim ke konsumsi</flux:button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</div>
