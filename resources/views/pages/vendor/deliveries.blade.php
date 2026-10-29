<?php

use App\Models\MealDelivery;
use App\Models\MealSchedule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengantaran')] class extends Component {
    public function with(): array
    {
        $user = auth()->user();
        abort_unless($user->isVendor(), 403);

        return [
            'vendor' => $user->vendor,
            // Jadwal dari tim konsumsi untuk vendor ini, hari ini s.d. 7 hari ke depan.
            'upcoming' => MealSchedule::query()->where('vendor_id', $user->vendor_id)->with('delivery')
                ->whereDateBetween('date', today()->toDateString(), today()->addDays(7)->toDateString())
                ->orderBy('starts_at')->get()->groupBy(fn ($s) => $s->date->toDateString()),
            'history' => MealDelivery::query()->where('vendor_id', $user->vendor_id)->with('schedule')
                ->whereHas('schedule', fn ($q) => $q->whereDate('date', '<', today()))
                ->latest('ready_at')->limit(10)->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <x-page-header title="Pengantaran makanan" :subtitle="$vendor->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="camera" :href="route('vendor.deliveries.create')" wire:navigate>Lapor pengantaran</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <flux:callout icon="check-circle" color="green" :text="session('status')" />
    @endif

    @forelse ($upcoming as $date => $schedules)
        <section wire:key="vd-{{ $date }}">
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">{{ tanggal($date, 'l, j M') }}</h2>
            <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
                @foreach ($schedules as $schedule)
                    @php($delivery = $schedule->delivery)
                    <div class="flex items-center gap-3 px-4 py-3" wire:key="vs-{{ $schedule->id }}">
                        <flux:icon :name="$schedule->meal_type->icon()" class="size-5 shrink-0 text-hutan/40" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $schedule->meal_type->label() }}</p>
                            @if ($delivery)
                                <p class="truncate text-xs text-woka dark:text-woka-light">
                                    Dilaporkan · {{ $delivery->quantityLabel() }} · siap {{ jam($delivery->ready_at) }} · {{ $delivery->location }}
                                </p>
                            @else
                                <p class="truncate text-xs text-hutan/50 dark:text-santan/50">Belum dilaporkan · ambil {{ $schedule->windowLabel() }} · {{ angka($schedule->portions_ordered) }} porsi</p>
                            @endif
                        </div>
                        <flux:button size="sm" :variant="$delivery ? 'ghost' : 'primary'" :href="route('vendor.deliveries.create', ['jadwal' => $schedule->id])" wire:navigate>
                            {{ $delivery ? 'Ubah' : 'Lapor' }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <x-empty-state icon="truck" title="Belum ada jadwal dari tim konsumsi"
                       description="Tetap bisa melapor pengantaran lewat tombol Lapor pengantaran." />
    @endforelse

    @if ($history->isNotEmpty())
        <section>
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Riwayat</h2>
            <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
                @foreach ($history as $delivery)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm" wire:key="vh-{{ $delivery->id }}">
                        <span class="min-w-0 truncate">{{ tanggal($delivery->schedule->date, 'j M') }} · {{ $delivery->schedule->meal_type->label() }}</span>
                        <span class="shrink-0 text-hutan/60 dark:text-santan/60">{{ $delivery->quantityLabel() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
