<?php

use App\Actions\Meals\ReportMealDelivery;
use App\Enums\DeliveryUnit;
use App\Enums\MealType;
use App\Http\Requests\MealDeliveryRequest;
use App\Models\Location;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Lapor pengantaran')] class extends Component {
    use WithFileUploads;

    /** Jadwal dari tim konsumsi (opsional). */
    #[Url(as: 'jadwal')]
    public ?int $scheduleId = null;

    public string $date = '';

    public string $meal_type = '';

    public string $ready_time = '';

    public ?int $quantity = null;

    public string $unit = 'pcs';

    public string $menu = '';

    public string $location = '';

    public $menuPhoto = null;

    public $totalPhoto = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->isVendor(), 403);

        $schedule = $this->schedule();
        $delivery = $schedule?->delivery;

        if ($delivery) {
            $this->authorize('update', $delivery);
        }

        $this->date = ($schedule?->date ?? today())->toDateString();
        $this->meal_type = $schedule?->meal_type->value ?? $this->guessMealType()->value;
        $this->ready_time = ($delivery?->ready_at ?? $schedule?->starts_at)?->format('H:i') ?? now()->addMinutes(15)->format('H:i');
        $this->quantity = $delivery?->quantity ?? $schedule?->portions_ordered;
        $this->unit = $delivery?->unit->value ?? DeliveryUnit::Pcs->value;
        $this->menu = $delivery?->menu ?? (string) $schedule?->menu;
        // Tempat terakhir yang dipakai vendor ini sebagai bawaan.
        $this->location = $delivery?->location
            ?? MealDelivery::query()->where('vendor_id', $user->vendor_id)->latest()->value('location')
            ?? '';
    }

    protected function schedule(): ?MealSchedule
    {
        return $this->scheduleId
            ? MealSchedule::query()->where('vendor_id', auth()->user()->vendor_id)->with('delivery')->findOrFail($this->scheduleId)
            : null;
    }

    /** Waktu makan terdekat dari jam sekarang. */
    protected function guessMealType(): MealType
    {
        $now = now()->format('H:i');

        return collect(MealType::cases())->first(fn (MealType $type) => $now <= $type->defaultWindow()[1]) ?? MealType::Dinner;
    }

    public function save(ReportMealDelivery $report): void
    {
        $this->resetErrorBag();
        $schedule = $this->schedule();
        $delivery = $schedule?->delivery;
        $delivery ? $this->authorize('update', $delivery) : $this->authorize('create', MealDelivery::class);

        $data = [
            'meal_schedule_id' => $schedule?->id, 'date' => $this->date, 'meal_type' => $this->meal_type,
            'ready_time' => $this->ready_time, 'quantity' => $this->quantity, 'unit' => $this->unit,
            'menu' => $this->menu, 'location' => $this->location,
            'menu_photo' => $this->menuPhoto, 'total_photo' => $this->totalPhoto,
        ];

        $validator = Validator::make($data, MealDeliveryRequest::rulesFor($schedule === null, $delivery === null), [], MealDeliveryRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->messages());
        }

        try {
            $saved = $report->handle(auth()->user(), $validator->validated(), $this->menuPhoto, $this->totalPhoto);
        } catch (\InvalidArgumentException $e) {
            $this->addError('menu', $e->getMessage());

            return;
        }

        session()->flash('status', $saved->schedule->meal_type->label().' '.tanggal($saved->schedule->date, 'j M').' tersimpan. Peserta diberi tahu pukul '.jam($saved->ready_at).' WITA.');
        $this->redirectRoute('vendor.deliveries.index', navigate: true);
    }

    public function with(): array
    {
        $schedule = $this->schedule();

        return [
            'schedule' => $schedule,
            'delivery' => $schedule?->delivery,
            'types' => MealType::cases(),
            'units' => DeliveryUnit::cases(),
            'places' => Location::query()->orderBy('name')->pluck('name')
                ->merge(MealDelivery::query()->where('vendor_id', auth()->user()->vendor_id)->distinct()->pluck('location'))
                ->unique()->values(),
        ];
    }
}; ?>

<div class="mx-auto max-w-lg">
    <x-page-header :title="$delivery ? 'Ubah laporan' : 'Lapor pengantaran'" :back="route('vendor.deliveries.index')"
                   :subtitle="$schedule ? $schedule->meal_type->label().' · '.tanggal($schedule->date, 'l, j M') : 'Isi singkat; peserta diberi tahu saat jam siap.'" />

    <form wire:submit="save" class="space-y-5">
        @unless ($schedule)
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="date" type="date" label="Tanggal terima" required />
                <flux:select wire:model="meal_type" label="Waktu makan">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endunless

        <div class="grid grid-cols-2 gap-3">
            <flux:input wire:model="quantity" type="number" min="1" inputmode="numeric" label="Jumlah" required />
            <flux:radio.group wire:model="unit" label="Satuan" variant="segmented">
                @foreach ($units as $unitOption)
                    <flux:radio :value="$unitOption->value" :label="$unitOption->label()" class="data-checked:bg-woka! data-checked:text-white!" />
                @endforeach
            </flux:radio.group>
        </div>

        <flux:textarea wire:model="menu" label="Menu" rows="3" required placeholder="Nasi putih&#10;Ikan woku belanga&#10;Tumis kangkung&#10;Pisang" description="Satu menu per baris." />

        <flux:input wire:model="ready_time" type="time" label="Jam siap diambil" required description="Peserta diberi tahu tepat pada jam ini." />

        <div>
            <flux:input wire:model="location" label="Tempat makanan tersedia" list="meal-places" required placeholder="Ruang makan asrama" />
            <datalist id="meal-places">
                @foreach ($places as $place)
                    <option value="{{ $place }}"></option>
                @endforeach
            </datalist>
        </div>

        <div class="grid grid-cols-2 gap-3">
            @foreach (['menuPhoto' => ['Foto menu', 'menu_photo', 'menu'], 'totalPhoto' => ['Foto total makanan', 'total_photo', 'total']] as $model => [$label, $error, $type])
                <label class="block cursor-pointer" wire:key="ph-{{ $model }}">
                    <span class="mb-1 block text-sm font-medium">{{ $label }}</span>
                    <span class="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-zinc-50 ring-1 ring-zinc-200 dark:bg-hutan-900 dark:ring-hutan-800">
                        @if ($this->{$model} && $this->{$model}->isPreviewable())
                            <img src="{{ $this->{$model}->temporaryUrl() }}" alt="{{ $label }}" class="size-full object-cover">
                        @elseif ($this->{$model})
                            {{-- Mis. foto HEIC dari iPhone: tidak bisa dipratinjau, tetap terkirim. --}}
                            <span class="flex flex-col items-center gap-1 px-2 text-center text-xs text-woka">
                                <flux:icon.check-circle class="size-7" /> Foto terpilih
                            </span>
                        @elseif ($delivery?->photoUrl($type))
                            <img src="{{ $delivery->photoUrl($type) }}" alt="{{ $label }}" class="size-full object-cover">
                        @else
                            <span class="flex flex-col items-center gap-1 text-xs text-hutan/50 dark:text-santan/50">
                                <flux:icon.camera class="size-7" /> Ambil foto
                            </span>
                        @endif
                        <span wire:loading wire:target="{{ $model }}" class="absolute inset-0 flex items-center justify-center bg-white/70 text-xs dark:bg-hutan-900/70">Mengunggah…</span>
                    </span>
                    <input type="file" accept="image/*" capture="environment" wire:model="{{ $model }}" class="sr-only">
                    <flux:error :name="$error" />
                </label>
            @endforeach
        </div>

        <flux:button type="submit" variant="primary" class="w-full" icon="paper-airplane">{{ $delivery ? 'Simpan perubahan' : 'Kirim laporan' }}</flux:button>
    </form>
</div>
