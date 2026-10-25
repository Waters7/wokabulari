<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Http\Requests\DormExitPermitRequest;
use App\Models\DormExitPermit;
use App\Services\QrCodeService;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Izin keluar asrama')] class extends Component {
    use WithFileUploads;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public $attachment = null;

    public ?int $qrFor = null;

    public bool $showQr = false;

    public function create(): void
    {
        $this->authorize('create', DormExitPermit::class);
        $this->resetErrorBag();
        $this->form = [
            'type' => ExitPermitType::Temporary->value,
            'reason' => '',
            'destination' => '',
            'companion' => '',
            'contact_phone' => auth()->user()->phone ? '0'.substr(auth()->user()->phone, 2) : '',
            'planned_departure' => now()->addHours(4)->startOfHour()->format('Y-m-d\TH:i'),
            'planned_return' => now()->addHours(7)->startOfHour()->format('Y-m-d\TH:i'),
        ];
        $this->attachment = null;
        $this->showForm = true;
    }

    public function save(ManageExitPermits $permits): void
    {
        $this->authorize('create', DormExitPermit::class);
        $this->resetErrorBag();

        $validator = Validator::make(array_merge($this->form, ['attachment' => $this->attachment]), DormExitPermitRequest::rulesFor(), [], DormExitPermitRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'attachment' ? 'attachment' : 'form.'.$k => $m])->all());
        }

        try {
            $permit = $permits->submit(auth()->user(), $validator->validated(), $this->attachment);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.planned_departure', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: $permit->guardian_required ? 'Pengajuan terkirim ke wali untuk disetujui dulu.' : 'Pengajuan terkirim ke pengelola asrama.');
    }

    public function cancel(int $id, ManageExitPermits $permits): void
    {
        $permit = DormExitPermit::query()->where('user_id', auth()->id())->findOrFail($id);
        $this->authorize('cancel', $permit);

        try {
            $permits->cancel($permit, auth()->user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(text: 'Izin dibatalkan.');
    }

    public function openQr(int $id): void
    {
        $this->qrFor = $id;
        $this->showQr = true;
    }

    public function with(QrCodeService $qr): array
    {
        $permits = DormExitPermit::query()->where('user_id', auth()->id())->latest('planned_departure')->limit(20)->get();
        $qrPermit = $this->qrFor ? $permits->firstWhere('id', $this->qrFor) : null;

        return [
            'permits' => $permits,
            'types' => ExitPermitType::cases(),
            'guardianTypes' => (array) setting('exit.guardian_required_types', []),
            'maxPerWeek' => setting('exit.max_per_week', 2),
            'minHours' => setting('exit.min_hours_before', 3),
            'qrPermit' => $qrPermit,
            'qrSvg' => $qrPermit?->status->isActive() && $qrPermit->qr_token ? $qr->svg($qrPermit->qr_token, 260) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Izin keluar asrama" subtitle="Maks. {{ $maxPerWeek }}× per minggu, diajukan paling lambat {{ $minHours }} jam sebelum keluar.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Ajukan izin</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-3">
        @forelse ($permits as $permit)
            <x-card wire:key="ex-{{ $permit->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $permit->type->label() }} · {{ $permit->destination }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $permit->periodLabel() }} WITA</p>
                        @if ($permit->guardian_required && $permit->status === ExitPermitStatus::Submitted)
                            <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Menunggu persetujuan wali.</p>
                        @endif
                        @if ($permit->approval_note || $permit->guardian_note)
                            <p class="mt-1 text-xs text-hutan/70 dark:text-santan/70">Catatan: {{ $permit->approval_note ?: $permit->guardian_note }}</p>
                        @endif
                        @if ($permit->actual_departure)
                            <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Keluar {{ tanggal_jam($permit->actual_departure) }}@if ($permit->actual_return) · kembali {{ tanggal_jam($permit->actual_return) }}@endif</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-status-badge :status="$permit->status" />
                        @if ($permit->was_late && $permit->status === ExitPermitStatus::Returned)
                            <flux:badge size="sm" color="rose">Terlambat</flux:badge>
                        @endif
                    </div>
                </div>
                @if ($permit->status->isActive() || $permit->status->isPending())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($permit->status->isActive())
                            <flux:button size="sm" variant="primary" icon="qr-code" wire:click="openQr({{ $permit->id }})">Tampilkan QR izin</flux:button>
                        @endif
                        @if ($permit->status->isPending() || $permit->status === ExitPermitStatus::Approved)
                            <x-confirm-button action="cancel({{ $permit->id }})" label="Batalkan" icon="x-mark" title="Batalkan izin ini?" message="Izin yang dibatalkan tidak bisa dipakai lagi." confirmLabel="Ya, batalkan" />
                        @endif
                    </div>
                @endif
            </x-card>
        @empty
            <x-empty-state icon="arrow-right-start-on-rectangle" title="Belum ada izin keluar" description="Ajukan izin sebelum keluar asrama. Setelah disetujui, tunjukkan QR ke petugas saat keluar dan kembali." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showQr" class="w-full max-w-sm">
        @if ($qrSvg)
            <div class="text-center" wire:key="qr-{{ $qrPermit->id }}">
                <flux:heading size="lg">QR izin keluar</flux:heading>
                <flux:text>{{ $qrPermit->type->label() }} · {{ $qrPermit->destination }}</flux:text>
                <div class="mx-auto my-4 w-64 rounded-2xl bg-white p-3">{!! $qrSvg !!}</div>
                <x-status-badge :status="$qrPermit->status" />
                <p class="mt-2 text-sm text-hutan/70 dark:text-santan/70">Tunjukkan ke petugas asrama saat keluar dan saat kembali.</p>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Ajukan izin keluar</flux:heading>
            <flux:select wire:model.live="form.type" label="Jenis izin">
                @foreach ($types as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if (in_array($form['type'] ?? '', $guardianTypes, true))
                <flux:callout icon="user-group" color="sky" text="Jenis ini perlu persetujuan wali dulu (bila wali sudah terhubung), lalu diteruskan ke pengelola asrama." />
            @endif
            <flux:textarea wire:model="form.reason" label="Alasan" rows="2" />
            <flux:input wire:model="form.destination" label="Tujuan" placeholder="Rumah keluarga di Tomohon" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.companion" label="Pendamping (opsional)" />
                <flux:input wire:model="form.contact_phone" label="Nomor kontak" inputmode="tel" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.planned_departure" type="datetime-local" label="Rencana keluar" />
                <flux:input wire:model="form.planned_return" type="datetime-local" label="Rencana kembali" />
            </div>
            <flux:input type="file" wire:model="attachment" label="Lampiran (opsional)" accept="image/*,application/pdf" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
