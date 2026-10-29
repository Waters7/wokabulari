{{-- Satu jadwal makan di halaman Input konsumsi: vendor, status input, tombol input/ubah. --}}
@php($delivery = $schedule->delivery)
<div class="flex items-center gap-3 px-4 py-3" wire:key="dr-{{ $schedule->id }}">
    <flux:icon :name="$schedule->meal_type->icon()" @class(['size-5 shrink-0', 'text-woka dark:text-woka-light' => $delivery, 'text-hutan/40' => ! $delivery]) />
    <div class="min-w-0 flex-1">
        <p class="font-semibold">{{ $schedule->meal_type->label() }} <span class="font-normal text-hutan/60 dark:text-santan/60">· {{ $schedule->vendor->name }}</span></p>
        @if ($delivery)
            <p class="truncate text-xs text-woka dark:text-woka-light">
                {{ $delivery->quantityLabel() }} · siap {{ jam($delivery->ready_at) }} · {{ $delivery->location }}
            </p>
            <p class="truncate text-xs text-hutan/50 dark:text-santan/50">
                {{ angka($schedule->receipts_count) }} tanda terima · diinput {{ $delivery->reporter?->name ?? '–' }}
            </p>
        @else
            <p class="truncate text-xs text-hutan/50 dark:text-santan/50">Dijadwalkan · ambil {{ $schedule->windowLabel() }} · {{ angka($schedule->portions_ordered) }} porsi · belum diinput</p>
        @endif
    </div>
    @can($delivery ? 'update' : 'create', $delivery ?? \App\Models\MealDelivery::class)
        <flux:button size="sm" :variant="$delivery ? 'ghost' : 'primary'" :href="route('meals.deliveries.create', ['jadwal' => $schedule->id])" wire:navigate>
            {{ $delivery ? 'Ubah' : 'Input' }}
        </flux:button>
    @endcan
</div>
