{{-- Beranda vendor makanan: jadwal hari ini dan status laporannya. --}}
@php
    $todaySchedules = \App\Models\MealSchedule::query()->where('vendor_id', auth()->user()->vendor_id)->with('delivery')
        ->onDate(today())->orderBy('starts_at')->get();
@endphp

<section class="space-y-3">
    <a href="{{ route('vendor.deliveries.create') }}" wire:navigate
       class="flex items-center gap-3 rounded-2xl bg-woka px-4 py-4 text-white transition hover:bg-woka-700">
        <flux:icon.camera class="size-6 shrink-0" />
        <span class="min-w-0 flex-1">
            <span class="block font-semibold">Lapor pengantaran</span>
            <span class="block text-xs text-white/80">Jumlah, menu, jam siap, tempat, dan foto.</span>
        </span>
        <flux:icon.chevron-right class="size-5 shrink-0" />
    </a>

    <div>
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Hari ini</h2>
        @forelse ($todaySchedules as $schedule)
            <a href="{{ route('vendor.deliveries.create', ['jadwal' => $schedule->id]) }}" wire:navigate wire:key="vt-{{ $schedule->id }}"
               class="mb-2 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:ring-hutan-800">
                <flux:icon :name="$schedule->meal_type->icon()" class="size-5 shrink-0 text-hutan/40" />
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">{{ $schedule->meal_type->label() }}</span>
                    <span class="block truncate text-xs text-hutan/50 dark:text-santan/50">
                        {{ $schedule->delivery ? 'Dilaporkan · siap '.jam($schedule->delivery->ready_at).' · '.$schedule->delivery->location : 'Belum dilaporkan · '.angka($schedule->portions_ordered).' porsi' }}
                    </span>
                </span>
                @if ($schedule->delivery)
                    <flux:icon.check-circle class="size-5 shrink-0 text-woka" />
                @else
                    <flux:badge size="sm" color="amber">Lapor</flux:badge>
                @endif
            </a>
        @empty
            <p class="rounded-2xl bg-white px-4 py-3 text-sm text-hutan/60 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:text-santan/60 dark:ring-hutan-800">
                Belum ada jadwal dari tim konsumsi untuk hari ini.
            </p>
        @endforelse
    </div>
</section>
