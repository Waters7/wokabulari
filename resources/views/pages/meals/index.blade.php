<?php

use App\Actions\Meals\ReceiveMeal;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Konsumsi')] class extends Component {
    public function receive(int $scheduleId, string $signature, ReceiveMeal $receive): void
    {
        $schedule = MealSchedule::query()->findOrFail($scheduleId);
        $this->authorize('receive', $schedule);

        try {
            $receive->handle($schedule, auth()->user(), $signature);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Makanan diterima. Selamat makan, neh!');
    }

    public function with(): array
    {
        $user = auth()->user();
        $today = MealSchedule::query()->with('vendor')->onDate(today())->orderBy('starts_at')->get();
        $received = MealReceipt::query()->where('user_id', $user->id)->whereIn('meal_schedule_id', $today->pluck('id'))->get()->keyBy('meal_schedule_id');

        return [
            'today' => $today,
            'received' => $received,
            'active' => $today->first(fn ($s) => $s->isOpen() && ! $received->has($s->id)),
            'profile' => $user->profile,
            'history' => MealReceipt::query()->with(['schedule.vendor', 'proxy'])->where('user_id', $user->id)
                ->whereNotIn('meal_schedule_id', $today->pluck('id'))->latest('received_at')->limit(15)->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl" wire:poll.60s>
    <x-page-header title="Konsumsi" subtitle="{{ tanggal_panjang(today()) }}">
        <x-slot:actions>
            <flux:button :href="route('ratings.index')" wire:navigate icon="star" size="sm">Rating mingguan</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($profile?->hasAllergies())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-4">
            <flux:callout.text>Catatan alergi/pantangan ngana: <strong>{{ $profile->allergies }}</strong>. Sudah diteruskan ke unit konsumsi; tetap periksa makananmu, neh.</flux:callout.text>
        </flux:callout>
    @endif

    @if ($active)
        <x-card class="mb-4 ring-2 ring-kunyit" wire:key="active-{{ $active->id }}">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <x-status-badge :status="$active->meal_type" />
                    <p class="mt-1 font-display text-xl font-bold">{{ $active->menu }}</p>
                    <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $active->vendor->name }} · ambil {{ $active->windowLabel() }} WITA</p>
                </div>
                <flux:icon :name="$active->meal_type->icon()" class="size-8 shrink-0 text-kunyit" />
            </div>
            <p class="mb-2 text-sm">Tanda tangan sebagai bukti ngana so terima makanan.</p>
            <x-signature-pad action="receive" :args="[$active->id]" wire:key="sig-{{ $active->id }}" />
        </x-card>
    @endif

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Jadwal hari ini</h2>
    <div class="mb-6 space-y-2">
        @forelse ($today as $schedule)
            @php($receipt = $received->get($schedule->id))
            <x-card wire:key="today-{{ $schedule->id }}"><div class="flex items-center gap-3">
                <span @class(['flex size-11 shrink-0 items-center justify-center rounded-xl', 'bg-woka-50 text-woka dark:bg-hutan-800' => $receipt, 'bg-santan text-hutan/60 dark:text-santan/60 dark:bg-hutan-800' => ! $receipt])>
                    <flux:icon :name="$receipt ? 'check-circle' : $schedule->meal_type->icon()" class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $schedule->meal_type->label() }} <span class="font-normal text-hutan/60 dark:text-santan/60">· {{ $schedule->windowLabel() }}</span></p>
                    <p class="truncate text-sm">{{ $schedule->menu }}</p>
                </div>
                <div class="shrink-0 text-end text-xs">
                    @if ($receipt)
                        <flux:badge color="green" size="sm">Diterima {{ jam($receipt->received_at) }}</flux:badge>
                    @elseif ($schedule->isOpen())
                        <flux:badge color="amber" size="sm">Sedang dibuka</flux:badge>
                    @elseif (now()->lt($schedule->starts_at))
                        <flux:badge size="sm">Dibuka {{ jam($schedule->starts_at) }}</flux:badge>
                    @else
                        <flux:badge color="red" size="sm">Tidak diambil</flux:badge>
                    @endif
                </div>
            </div></x-card>
        @empty
            <x-empty-state icon="cake" title="Belum ada jadwal makan hari ini" description="Jadwal diatur oleh unit konsumsi." />
        @endforelse
    </div>

    @if ($history->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Riwayat penerimaan</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($history as $receipt)
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="h-{{ $receipt->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $receipt->schedule?->meal_type->label() }} · {{ tanggal($receipt->schedule?->date, 'D, j M') }}</p>
                        <p class="truncate text-hutan/60 dark:text-santan/60">{{ $receipt->schedule?->vendor?->name }}@if ($receipt->isProxy()) · diwakilkan {{ $receipt->proxy?->name }}@endif</p>
                    </div>
                    <span class="shrink-0 text-hutan/60 dark:text-santan/60">{{ jam($receipt->received_at) }}</span>
                </div>
            @endforeach
        </x-card>
    @endif
</div>
