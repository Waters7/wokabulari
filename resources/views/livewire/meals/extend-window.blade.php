<?php

use App\Actions\Meals\ExtendMealWindow;
use App\Http\Requests\ExtendMealWindowRequest;
use App\Models\MealSchedule;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Tombol "Makanan terlambat": perpanjang waktu ambil & tanda terima satu jadwal makan.
 */
new class extends Component {
    public MealSchedule $schedule;

    /** Tampilkan teks pada tombol (false = ikon saja). */
    public bool $withLabel = true;

    public bool $open = false;

    public string $until = '';

    public string $note = 'Makanan terlambat dari vendor';

    public bool $notify = true;

    /** Dasar hitung tambahan menit: jam selesai sekarang, atau sekarang bila sudah lewat. */
    protected function base(): CarbonImmutable
    {
        $ends = $this->schedule->ends_at->toImmutable();

        return $this->schedule->date->isToday() && now()->gt($ends) ? now()->toImmutable() : $ends;
    }

    public function addMinutes(int $minutes): void
    {
        $until = $this->base()->addMinutes($minutes)->setSecond(0);
        // Bulatkan ke atas ke kelipatan 5 menit agar mudah dibaca peserta; paling lambat 23.59.
        $until = $until->addMinutes((5 - $until->minute % 5) % 5);
        $latest = $this->schedule->date->setTime(23, 59);
        $this->until = ($until->gt($latest) ? $latest : $until)->format('H:i');
    }

    public function openForm(): void
    {
        $this->authorize('update', $this->schedule);
        $this->resetErrorBag();
        $this->addMinutes(30);
        $this->open = true;
    }

    public function save(ExtendMealWindow $extend): void
    {
        $this->authorize('update', $this->schedule);
        $this->resetErrorBag();

        $validator = Validator::make(['until' => $this->until, 'note' => $this->note, 'notify' => $this->notify], ExtendMealWindowRequest::rulesFor(), [], ['until' => 'jam baru', 'note' => 'catatan']);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->messages());
        }

        try {
            $notified = $extend->handle($this->schedule, auth()->user(), $this->until, $this->note, $this->notify);
        } catch (\InvalidArgumentException $e) {
            $this->addError('until', $e->getMessage());

            return;
        }

        $this->open = false;
        $this->dispatch('meal-window-extended');
        Flux::toast(variant: 'success', text: $this->schedule->meal_type->label().' bisa diambil & ditandatangani sampai '.jam($this->schedule->fresh()->ends_at).'.'.($notified ? " {$notified} penerima diberi tahu." : ''));
    }
}; ?>

<div class="inline-flex">
    @if ($withLabel)
        <flux:button size="sm" icon="clock" wire:click="openForm" data-test="extend-window">Makanan terlambat</flux:button>
    @else
        <flux:button size="xs" variant="ghost" icon="clock" wire:click="openForm" aria-label="Makanan terlambat: perpanjang waktu ambil" data-test="extend-window" />
    @endif

    <flux:modal wire:model.self="open" class="w-full max-w-sm">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Perpanjang waktu ambil</flux:heading>
                <flux:text class="mt-1">
                    {{ $schedule->meal_type->label() }} {{ tanggal($schedule->date, 'j M') }} · sekarang {{ $schedule->windowLabel() }} WITA.
                    Peserta & tim tetap bisa mengambil dan menandatangani tanda terima sampai jam baru.
                </flux:text>
            </div>
            <div>
                <div class="mb-2 flex flex-wrap gap-1.5">
                    @foreach ([15, 30, 60, 90] as $minutes)
                        <flux:button size="xs" wire:click="addMinutes({{ $minutes }})">+{{ $minutes }} mnt</flux:button>
                    @endforeach
                </div>
                <flux:input type="time" wire:model="until" label="Bisa diambil sampai" />
            </div>
            <flux:input wire:model="note" label="Catatan" placeholder="Makanan terlambat dari vendor" />
            <flux:switch wire:model="notify" label="Beri tahu yang belum mengambil" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Perpanjang</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
