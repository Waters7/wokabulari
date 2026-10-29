<?php

use App\Enums\MealType;
use App\Models\MealDelivery;
use App\Models\MealSchedule;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Input konsumsi')] class extends Component {
    #[Url(as: 'tanggal')]
    public string $date = '';

    public function mount(): void
    {
        $this->authorize('create', MealDelivery::class);
        $this->date = $this->date ?: today()->toDateString();
    }

    public function shift(int $days): void
    {
        $this->date = CarbonImmutable::parse($this->date)->addDays($days)->toDateString();
    }

    public function with(): array
    {
        $schedules = MealSchedule::query()->with(['vendor', 'delivery.reporter'])->withCount('receipts')
            ->onDate($this->date)->orderBy('starts_at')->get();
        $types = collect(MealType::cases());

        return [
            'day' => CarbonImmutable::parse($this->date),
            // Makan utama selalu tampil; snack hanya bila tim konsumsi menambahkannya.
            'mainTypes' => $types->reject->isSnack(),
            'snackTypes' => $types->filter->isSnack(),
            'schedules' => $schedules->groupBy(fn ($s) => $s->meal_type->value),
            'snacks' => $schedules->filter(fn ($s) => $s->meal_type->isSnack()),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header title="Input konsumsi" subtitle="Catat makanan yang datang: vendor, jumlah, menu, jam siap, tempat, dan foto. Peserta diberi tahu pada jam siap.">
        <x-slot:actions>
            <flux:button icon="calendar-days" :href="route('meals.schedules')" wire:navigate>Jadwal</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <flux:callout icon="check-circle" color="green" :text="session('status')" />
    @endif

    <div class="flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Hari sebelumnya" />
        <p class="min-w-0 flex-1 text-center font-display text-lg font-bold">{{ tanggal($day, 'l, j M Y') }}</p>
        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Hari berikutnya" />
    </div>

    @php($row = 'flex items-center gap-3 px-4 py-3')
    <section>
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Makan utama</h2>
        <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
            @foreach ($mainTypes as $type)
                @forelse ($schedules[$type->value] ?? [] as $schedule)
                    @include('partials.meal-delivery-row', ['schedule' => $schedule])
                @empty
                    <div class="{{ $row }}" wire:key="dm-{{ $type->value }}">
                        <flux:icon :name="$type->icon()" class="size-5 shrink-0 text-hutan/40" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $type->label() }}</p>
                            <p class="text-xs text-hutan/50 dark:text-santan/50">Belum diinput</p>
                        </div>
                        <flux:button size="sm" variant="primary" :href="route('meals.deliveries.create', ['tanggal' => $day->toDateString(), 'jenis' => $type->value])" wire:navigate>Input</flux:button>
                    </div>
                @endforelse
            @endforeach
        </div>
    </section>

    <section>
        <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Snack</h2>
        <p class="mb-2 text-xs text-hutan/50 dark:text-santan/50">Tambahkan hanya bila snack tersedia hari ini.</p>
        @if ($snacks->isNotEmpty())
            <div class="mb-2 divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
                @foreach ($snacks as $schedule)
                    @include('partials.meal-delivery-row', ['schedule' => $schedule])
                @endforeach
            </div>
        @endif
        <div class="grid grid-cols-2 gap-2">
            @foreach ($snackTypes as $type)
                <a href="{{ route('meals.deliveries.create', ['tanggal' => $day->toDateString(), 'jenis' => $type->value]) }}" wire:navigate wire:key="ds-{{ $type->value }}"
                   class="flex min-h-11 items-center justify-center gap-1 rounded-2xl border border-dashed border-woka-200 text-sm text-hutan/60 hover:bg-woka-50 dark:border-hutan-800 dark:text-santan/60">
                    <flux:icon.plus class="size-4" /> {{ $type->label() }}
                </a>
            @endforeach
        </div>
    </section>
</div>
