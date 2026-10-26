<?php

use App\Actions\Dorm\AssignRooms;
use App\Http\Requests\DormitoryRequest;
use App\Models\Dormitory;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kamar asrama')] class extends Component {
    public Dormitory $dormitory;

    public bool $showRoom = false;

    public ?int $roomId = null;

    /** @var array<string, mixed> */
    public array $room = ['number' => '', 'floor' => '1', 'capacity' => 4];

    public ?int $assignRoomId = null;

    public string $search = '';

    public function mount(Dormitory $dormitory): void
    {
        $this->authorize('view', $dormitory);
        $this->dormitory = $dormitory;
    }

    public function createRoom(): void
    {
        $this->authorize('update', $this->dormitory);
        $this->resetValidation();
        $this->roomId = null;
        $this->room = ['number' => '', 'floor' => '1', 'capacity' => 4];
        $this->showRoom = true;
    }

    public function editRoom(int $id): void
    {
        $this->authorize('update', $this->dormitory);
        $room = $this->dormitory->rooms()->findOrFail($id);
        $this->roomId = $room->id;
        $this->room = $room->only(['number', 'floor', 'capacity']);
        $this->showRoom = true;
    }

    public function saveRoom(): void
    {
        $this->authorize('update', $this->dormitory);
        $this->resetErrorBag();
        $validator = Validator::make($this->room, DormitoryRequest::roomRules($this->dormitory->id, $this->roomId), [], ['number' => 'nomor kamar', 'capacity' => 'kapasitas']);

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["room.{$k}" => $m])->all());
        }

        $this->dormitory->rooms()->updateOrCreate(['id' => $this->roomId], $validator->validated());
        $this->showRoom = false;
        Flux::toast(variant: 'success', text: 'Kamar tersimpan.');
    }

    public function deleteRoom(int $id): void
    {
        $this->authorize('update', $this->dormitory);
        $room = $this->dormitory->rooms()->findOrFail($id);

        if ($room->activeAssignments()->exists()) {
            Flux::toast(variant: 'danger', text: 'Kamar masih berpenghuni.');

            return;
        }

        $room->delete();
        Flux::toast(text: 'Kamar dihapus.');
    }

    public function openAssign(int $roomId): void
    {
        $this->authorize('update', $this->dormitory);
        $this->assignRoomId = $roomId;
        $this->search = '';
    }

    public function assign(int $userId, AssignRooms $rooms): void
    {
        $this->authorize('update', $this->dormitory);
        $room = $this->dormitory->rooms()->findOrFail($this->assignRoomId);
        $user = User::query()->participants()->findOrFail($userId);

        try {
            // Pindah dari kamar lama; peserta tidak asrama otomatis menjadi penghuni.
            $rooms->assign($room, $user, auth()->user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: "{$user->name} ditempatkan di kamar {$room->number}.");
    }

    public function checkout(int $assignmentId): void
    {
        $this->authorize('update', $this->dormitory);
        $assignment = DormRoomAssignment::query()->whereHas('room', fn ($q) => $q->where('dormitory_id', $this->dormitory->id))->findOrFail($assignmentId);
        $assignment->update(['check_out_date' => today()]);
        Flux::toast(text: 'Penghuni dikeluarkan dari kamar.');
    }

    public function with(): array
    {
        return [
            'rooms' => $this->dormitory->rooms()->with(['activeAssignments.user.profile'])->get(),
            'candidates' => ($this->assignRoomId && filled($this->search)) ? User::query()
                ->participants()
                ->search($this->search)
                ->whereHas('profile', fn ($q) => $q->where('gender', $this->dormitory->gender->value)->orWhereNull('gender'))
                ->with('currentRoomAssignment.room')
                ->limit(10)
                ->get() : collect(),
        ];
    }
}; ?>

<div>
    <x-page-header :title="$dormitory->name" :back="route('master.dormitories')" :subtitle="'Asrama '.$dormitory->gender->dormLabel().' · Absen malam '.jam($dormitory->check_window_start).'–'.jam($dormitory->check_window_end)">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createRoom">Kamar baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($rooms as $room)
            <x-card wire:key="room-{{ $room->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display text-lg font-bold">Kamar {{ $room->number }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">Lantai {{ $room->floor ?? '–' }} · {{ $room->activeAssignments->count() }}/{{ $room->capacity }} penghuni</p>
                    </div>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editRoom({{ $room->id }})" aria-label="Ubah kamar" />
                        <x-confirm-button action="deleteRoom({{ $room->id }})" title="Hapus kamar {{ $room->number }}?" />
                    </div>
                </div>
                <ul class="mt-3 space-y-1">
                    @foreach ($room->activeAssignments as $assignment)
                        <li class="flex items-center justify-between gap-2 rounded-lg bg-santan px-2 py-1.5 text-sm dark:bg-hutan-950" wire:key="as-{{ $assignment->id }}">
                            <span class="truncate">{{ $assignment->user->name }}</span>
                            <x-confirm-button action="checkout({{ $assignment->id }})" icon="arrow-right-start-on-rectangle" title="Keluarkan {{ $assignment->user->name }} dari kamar?" confirmLabel="Keluarkan" />
                        </li>
                    @endforeach
                </ul>
                @if ($room->activeAssignments->count() < $room->capacity)
                    <flux:button class="mt-2 w-full" size="sm" icon="user-plus" wire:click="openAssign({{ $room->id }})">Tempatkan peserta</flux:button>
                @endif

                @if ($assignRoomId === $room->id)
                    <div class="mt-3 space-y-2 rounded-xl bg-woka-50 p-3 dark:bg-hutan-800">
                        <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass" placeholder="Cari nama peserta…" autofocus />
                        @foreach ($candidates as $candidate)
                            <button type="button" wire:click="assign({{ $candidate->id }})" class="flex w-full items-center justify-between gap-2 rounded-lg bg-white px-2 py-2 text-start text-sm hover:ring-1 hover:ring-woka dark:bg-hutan-900">
                                <span class="truncate font-semibold">{{ $candidate->name }}</span>
                                <span class="shrink-0 text-xs text-hutan/60 dark:text-santan/60">{{ $candidate->currentRoomAssignment ? 'Kamar '.$candidate->currentRoomAssignment->room->number : 'Belum ada kamar' }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="home-modern" title="Belum ada kamar" />
        @endforelse
    </div>

    <flux:modal wire:model.self="showRoom" class="w-full max-w-sm">
        <form wire:submit="saveRoom" class="space-y-4">
            <flux:heading size="lg">{{ $roomId ? 'Ubah kamar' : 'Kamar baru' }}</flux:heading>
            <flux:input wire:model="room.number" label="Nomor kamar" required />
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="room.floor" label="Lantai" />
                <flux:input wire:model="room.capacity" label="Kapasitas" type="number" min="1" max="20" required />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
