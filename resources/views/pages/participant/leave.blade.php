<?php

use App\Enums\LeaveType;
use App\Enums\ReviewStatus;
use App\Http\Requests\LeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestSubmitted;
use App\Services\ImageService;
use Flux\Flux;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Izin / sakit')] class extends Component {
    use WithFileUploads;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public $attachment = null;

    public function create(): void
    {
        $this->authorize('create', LeaveRequest::class);
        $this->resetErrorBag();
        $this->form = ['type' => LeaveType::Excused->value, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'reason' => ''];
        $this->attachment = null;
        $this->showForm = true;
    }

    public function save(ImageService $images): void
    {
        $this->authorize('create', LeaveRequest::class);
        $this->resetErrorBag();

        $validator = Validator::make(array_merge($this->form, ['attachment' => $this->attachment]), LeaveRequestRequest::rulesFor(), [], ['type' => 'jenis', 'date_from' => 'tanggal mulai', 'date_to' => 'tanggal selesai', 'reason' => 'alasan', 'attachment' => 'lampiran']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'attachment' ? 'attachment' : 'form.'.$k => $m])->all());
        }

        $data = $validator->validated();
        $path = null;
        if ($this->attachment) {
            $path = str_starts_with((string) $this->attachment->getMimeType(), 'image/')
                ? $images->storeUpload($this->attachment, 'leave-attachments')
                : $this->attachment->storeAs('leave-attachments', Str::uuid().'.pdf', 'private');
        }

        $leave = auth()->user()->leaveRequests()->create([
            'type' => $data['type'],
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'reason' => $data['reason'],
            'attachment_path' => $path,
            'status' => ReviewStatus::Pending,
        ]);

        // Kabari pengajar kelas peserta.
        $classroomIds = auth()->user()->classrooms()->pluck('classrooms.id');
        Notification::send(User::query()->whereHas('teachingClassrooms', fn ($c) => $c->whereIn('classrooms.id', $classroomIds))->get(), new LeaveRequestSubmitted($leave));

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Pengajuan terkirim. Semoga lekas beres, neh!');
    }

    public function cancel(int $id): void
    {
        $leave = auth()->user()->leaveRequests()->findOrFail($id);
        $this->authorize('delete', $leave);
        $leave->delete();
        Flux::toast(text: 'Pengajuan dibatalkan.');
    }

    public function with(): array
    {
        return [
            'leaves' => auth()->user()->leaveRequests()->with('reviewer')->latest()->get(),
            'types' => LeaveType::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Izin / sakit" subtitle="Ajukan izin atau sakit kelas. Disetujui pengajar atau admin program.">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Ajukan</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-2">
        @forelse ($leaves as $leave)
            <x-card wire:key="lv-{{ $leave->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$leave->type" />
                            <x-status-badge :status="$leave->status" />
                        </div>
                        <p class="mt-1 font-semibold">{{ $leave->periodLabel() }} <span class="font-normal text-hutan/60 dark:text-santan/60">({{ $leave->dayCount() }} hari)</span></p>
                        <p class="text-sm">{{ $leave->reason }}</p>
                        @if ($leave->review_note)
                            <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">Catatan {{ $leave->reviewer?->name }}: {{ $leave->review_note }}</p>
                        @endif
                    </div>
                    @if ($leave->status === ReviewStatus::Pending)
                        <x-confirm-button action="cancel({{ $leave->id }})" icon="x-mark" title="Batalkan pengajuan ini?" confirmLabel="Batalkan" />
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state icon="document-text" title="Belum ada pengajuan" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Ajukan izin / sakit</flux:heading>
            <flux:radio.group wire:model="form.type" variant="segmented">
                @foreach ($types as $type)
                    <flux:radio :value="$type->value">{{ $type->label() }}</flux:radio>
                @endforeach
            </flux:radio.group>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.date_from" type="date" label="Dari" />
                <flux:input wire:model="form.date_to" type="date" label="Sampai" />
            </div>
            <flux:textarea wire:model="form.reason" label="Alasan" rows="3" />
            <div>
                <flux:input type="file" wire:model="attachment" label="Lampiran surat (opsional)" accept="image/*,application/pdf" description="Foto surat dokter/izin atau PDF, maks. 5 MB." />
                <flux:error name="attachment" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kirim</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
