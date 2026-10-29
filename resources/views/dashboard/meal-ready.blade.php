{{-- Peserta: makanan yang sudah siap diambil (dari laporan vendor) dan belum diterima. --}}
@php
    $mealMe = auth()->user();
    $mealRoster = app(\App\Services\MealRoster::class);
    $readyMeals = \App\Models\MealSchedule::query()->with('delivery')->onDate(today())
        ->whereHas('delivery', fn ($q) => $q->where('ready_at', '<=', now()))
        ->where('ends_at', '>', now())
        ->whereDoesntHave('receipts', fn ($q) => $q->where('user_id', $mealMe->id))
        ->orderBy('starts_at')->get()
        ->filter(fn ($schedule) => $mealRoster->isEligible($mealMe, $schedule->meal_type));
@endphp

@foreach ($readyMeals as $readyMeal)
    <a href="{{ route('meals.index') }}" wire:navigate wire:key="mr-{{ $readyMeal->id }}" data-test="meal-ready"
       class="flex items-center gap-3 rounded-2xl border-s-4 border-woka bg-white px-4 py-3 ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800">
        <flux:icon :name="$readyMeal->meal_type->icon()" class="size-5 shrink-0 text-woka dark:text-woka-light" />
        <span class="min-w-0 flex-1">
            <span class="block font-semibold">{{ $readyMeal->meal_type->label() }} siap diambil</span>
            <span class="block truncate text-xs text-hutan/50 dark:text-santan/50">{{ $readyMeal->delivery->location }} · s.d. {{ jam($readyMeal->ends_at) }} WITA</span>
        </span>
        <flux:icon.chevron-right class="size-4 shrink-0 text-hutan/40" />
    </a>
@endforeach
