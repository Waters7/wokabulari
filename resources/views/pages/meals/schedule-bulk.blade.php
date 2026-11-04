<?php

use App\Actions\Meals\BulkMealSchedules;
use App\Enums\MealType;
use App\Http\Requests\BulkMealScheduleRequest;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Services\MealRoster;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Isi & ubah massal jadwal makan')] class extends Component {
    public const WEEKDAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /** isi = buat jadwal baru, ubah = ubah/hapus jadwal yang ada */
    #[Url]
    public string $mode = 'isi';

    #[Url(as: 'dari')]
    public string $start = '';

    /** @var array{from: string, to: string, weekdays: list<string>} */
    public array $range = [];

    /** @var list<string> Jenis makan yang dibuat */
    public array $fillTypes = [];

    /** @var array<string, array<string, mixed>> */
    public array $meals = [];

    public string $existing = 'skip';

    /** @var array<string, mixed> Filter & perubahan untuk ubah massal */
    public array $edit = [];

    /** @var array<string, mixed>|null Ringkasan proses terakhir */
    public ?array $result = null;

    public function mount(MealRoster $roster): void
    {
        $this->authorize('create', MealSchedule::class);
        $this->mode = in_array($this->mode, ['isi', 'ubah'], true) ? $this->mode : 'isi';

        $monday = $this->start !== '' ? CarbonImmutable::parse($this->start)->startOfWeek() : now()->addWeek()->startOfWeek()->toImmutable();
        $this->range = ['from' => $monday->toDateString(), 'to' => $monday->endOfWeek()->toDateString(), 'weekdays' => array_map('strval', array_keys(self::WEEKDAYS))];

        $vendor = (string) (Vendor::query()->active()->orderBy('name')->value('id') ?? '');
        $this->fillTypes = [MealType::Breakfast->value, MealType::Lunch->value, MealType::Dinner->value];
        foreach (MealType::cases() as $type) {
            [$from, $to] = $type->defaultWindow();
            $this->meals[$type->value] = ['vendor_id' => $vendor, 'start_time' => $from, 'end_time' => $to, 'portions_mode' => 'auto', 'portions' => '', 'menu' => ''];
        }

        $this->edit = [
            'types' => array_map(fn (MealType $t) => $t->value, MealType::cases()), 'vendor_filter' => '', 'changes' => [],
            'vendor_id' => $vendor, 'time_mode' => 'shift', 'start_time' => '', 'end_time' => '', 'shift_minutes' => '30',
            'portions_mode' => 'auto', 'portions' => '', 'menu' => '',
        ];
    }

    public function preset(string $preset): void
    {
        $today = now()->toImmutable()->startOfDay();
        [$from, $to] = match ($preset) {
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'two_weeks' => [$today->addWeek()->startOfWeek(), $today->addWeeks(2)->endOfWeek()],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'next_month' => [$today->addMonthNoOverflow()->startOfMonth(), $today->addMonthNoOverflow()->endOfMonth()],
            default => [$today->addWeek()->startOfWeek(), $today->addWeek()->endOfWeek()],
        };
        $this->range['from'] = $from->toDateString();
        $this->range['to'] = $to->toDateString();
        $this->result = null;
    }

    public function weekdaysPreset(string $preset): void
    {
        $this->range['weekdays'] = array_map('strval', $preset === 'weekdays' ? [1, 2, 3, 4, 5] : array_keys(self::WEEKDAYS));
    }

    /**
     * Validasi lalu tampilkan galat pada properti yang sesuai.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $map  awalan kunci galat → properti
     * @return array<string, mixed>
     */
    protected function check(array $data, array $rules, array $map): array
    {
        $this->resetErrorBag();
        $validator = Validator::make($data, $rules, ['weekdays.required' => 'Pilih minimal satu hari.', 'types.required' => 'Pilih minimal satu waktu makan.', 'changes.required' => 'Centang minimal satu perubahan.'], BulkMealScheduleRequest::attributesFor());

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(function ($messages, $key) use ($map) {
                $root = explode('.', $key)[0];

                return [($map[$root] ?? $root).(str_contains($key, '.') ? substr($key, strlen($root)) : '') => $messages];
            })->all());
        }

        return $validator->validated();
    }

    protected function rangeMap(): array
    {
        return ['from' => 'range.from', 'to' => 'range.to', 'weekdays' => 'range.weekdays'];
    }

    public function runFill(BulkMealSchedules $bulk): void
    {
        $this->authorize('create', MealSchedule::class);
        $types = array_values(array_intersect(array_map(fn (MealType $t) => $t->value, MealType::cases()), $this->fillTypes));
        $this->check($this->range + ['types' => $types, 'existing' => $this->existing, 'meals' => $this->meals],
            BulkMealScheduleRequest::fillRulesFor($types), $this->rangeMap() + ['types' => 'fillTypes']);

        try {
            $dates = $bulk->dates($this->range['from'], $this->range['to'], $this->range['weekdays']);
            $result = $bulk->fill($dates, collect($this->meals)->only($types)->all(), $this->existing, auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('range.to', $e->getMessage());

            return;
        }

        $this->result = ['mode' => 'isi', 'text' => "{$result['create']} jadwal dibuat, {$result['update']} diperbarui, {$result['skip']} dilewati.", 'from' => $this->range['from']];
        Flux::toast(variant: 'success', text: $this->result['text']);
    }

    /** @return array<string, mixed> data ubah massal yang sudah divalidasi */
    protected function validatedEdit(bool $withChanges): array
    {
        $data = $this->range + $this->edit;
        $rules = $withChanges
            ? BulkMealScheduleRequest::changeRulesFor($this->edit['changes'] ?? [], $this->edit['time_mode'] ?? 'set', $this->edit['portions_mode'] ?? 'auto')
            : BulkMealScheduleRequest::filterRules();
        $data['vendor_filter'] = filled($data['vendor_filter']) ? (int) $data['vendor_filter'] : null;

        return $this->check($data, $rules, $this->rangeMap() + collect($this->edit)->keys()->mapWithKeys(fn ($k) => [$k => "edit.{$k}"])->all());
    }

    public function runChange(BulkMealSchedules $bulk): void
    {
        $this->authorize('create', MealSchedule::class);
        $data = $this->validatedEdit(true);

        $changes = [];
        foreach ($data['changes'] as $change) {
            $changes += match ($change) {
                'vendor' => ['vendor_id' => (int) $data['vendor_id']],
                'time' => $data['time_mode'] === 'shift'
                    ? ['time_mode' => 'shift', 'shift_minutes' => (int) $data['shift_minutes']]
                    : ['time_mode' => 'set', 'start_time' => $data['start_time'], 'end_time' => $data['end_time']],
                'portions' => ['portions_mode' => $data['portions_mode'], 'portions' => (int) ($data['portions'] ?? 0)],
                'menu' => ['menu' => $data['menu']],
                default => [],
            };
        }

        try {
            $dates = $bulk->dates($data['from'], $data['to'], $data['weekdays']);
            $result = $bulk->change($bulk->matching($dates, $data['types'], $data['vendor_filter'] ?? null), $changes, auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('range.to', $e->getMessage());

            return;
        }

        $this->result = ['mode' => 'ubah', 'text' => trim("{$result['updated']} jadwal diubah, {$result['skipped']} dilewati. ".implode(' ', $result['notes'])), 'from' => $this->range['from']];
        Flux::toast(variant: 'success', text: "{$result['updated']} jadwal diubah.");
    }

    public function runDelete(BulkMealSchedules $bulk): void
    {
        $this->authorize('create', MealSchedule::class);
        $data = $this->validatedEdit(false);

        try {
            $dates = $bulk->dates($data['from'], $data['to'], $data['weekdays']);
            $result = $bulk->delete($bulk->matching($dates, $data['types'], $data['vendor_filter'] ?? null), auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('range.to', $e->getMessage());

            return;
        }

        $this->result = ['mode' => 'hapus', 'text' => "{$result['deleted']} jadwal dihapus".($result['skipped'] ? ", {$result['skipped']} dilewati karena sudah ada penerimaan." : '.'), 'from' => $this->range['from']];
        Flux::toast(text: $this->result['text']);
    }

    public function with(BulkMealSchedules $bulk, MealRoster $roster): array
    {
        $dates = collect();
        $rangeError = null;
        try {
            $dates = $bulk->dates($this->range['from'] ?: today()->toDateString(), $this->range['to'] ?: today()->toDateString(), $this->range['weekdays'] ?? []);
        } catch (\Throwable $e) {
            $rangeError = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Tanggal belum lengkap.';
        }

        $types = collect(MealType::cases());
        $fillTypes = $types->filter(fn (MealType $t) => in_array($t->value, $this->fillTypes, true));
        $plan = $this->mode === 'isi' && $dates->isNotEmpty() && $fillTypes->isNotEmpty()
            ? $bulk->preview($dates, collect($this->meals)->only($fillTypes->map->value->all())->all(), $this->existing)
            : ['create' => 0, 'update' => 0, 'skip' => 0, 'total' => 0];

        $matched = $this->mode === 'ubah' && $dates->isNotEmpty() && ($this->edit['types'] ?? []) !== []
            ? $bulk->matching($dates, $this->edit['types'], filled($this->edit['vendor_filter']) ? (int) $this->edit['vendor_filter'] : null)->with('vendor')->get()
            : collect();

        return [
            'types' => $types,
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'allVendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'dates' => $dates,
            'rangeError' => $rangeError,
            'recipients' => $types->mapWithKeys(fn (MealType $t) => [$t->value => $roster->recipients($t)->count()]),
            'plan' => $plan,
            'matched' => $matched,
            'locked' => $matched->where('receipts_count', '>', 0)->count(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Isi & ubah massal" subtitle="Buat atau ubah banyak jadwal makan sekaligus untuk rentang tanggal, tanpa satu per satu."
                   :back="route('meals.schedules', ['week' => CarbonImmutable::parse($range['from'] ?: today())->startOfWeek()->toDateString()])" />

    @php
        $label = 'text-xs font-bold uppercase tracking-wide text-woka dark:text-woka-light';
        $chip = 'flex min-h-10 cursor-pointer items-center justify-center rounded-full border border-woka-100 bg-white px-3 text-sm font-semibold has-[:checked]:border-woka has-[:checked]:bg-woka has-[:checked]:text-white dark:border-hutan-800 dark:bg-hutan-900';
    @endphp

    @if ($result)
        <flux:callout class="mb-4" icon="check-circle" color="green" data-test="bulk-result" :heading="$result['text']">
            <x-slot name="actions">
                <flux:button size="sm" :href="route('meals.schedules', ['week' => CarbonImmutable::parse($result['from'])->startOfWeek()->toDateString()])" wire:navigate>Lihat jadwal</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Langkah 1: rentang & hari --}}
    <x-card class="mb-3">
        <p class="{{ $label }} mb-2">1 · Rentang tanggal & hari</p>
        <div class="grid gap-2 sm:grid-cols-2">
            <flux:input type="date" wire:model.live.debounce.400ms="range.from" label="Dari" />
            <flux:input type="date" wire:model.live.debounce.400ms="range.to" label="Sampai" />
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach (['this_week' => 'Minggu ini', 'next_week' => 'Minggu depan', 'two_weeks' => '2 minggu ke depan', 'this_month' => 'Bulan ini', 'next_month' => 'Bulan depan'] as $key => $text)
                <flux:button size="xs" variant="ghost" wire:click="preset('{{ $key }}')">{{ $text }}</flux:button>
            @endforeach
        </div>

        <div class="mt-3 grid grid-cols-7 gap-1">
            @foreach ($this::WEEKDAYS as $day => $name)
                <label class="{{ $chip }} px-0" wire:key="wd-{{ $day }}">
                    <input type="checkbox" class="sr-only" value="{{ $day }}" wire:model.live="range.weekdays"> {{ $name }}
                </label>
            @endforeach
        </div>
        <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="flex gap-2">
                <button type="button" wire:click="weekdaysPreset('all')" class="font-semibold text-woka hover:underline dark:text-woka-light">Setiap hari</button>
                <button type="button" wire:click="weekdaysPreset('weekdays')" class="font-semibold text-woka hover:underline dark:text-woka-light">Senin–Jumat</button>
            </span>
            @if ($rangeError)
                <span class="font-semibold text-rica">{{ $rangeError }}</span>
            @elseif ($dates->isNotEmpty())
                <span class="text-hutan/60 dark:text-santan/60" data-test="day-count"><strong>{{ $dates->count() }} hari</strong> · {{ tanggal($dates->first(), 'j M') }} – {{ tanggal($dates->last(), 'j M Y') }}</span>
            @else
                <span class="font-semibold text-rica">Tidak ada tanggal yang cocok dengan hari terpilih.</span>
            @endif
        </div>
        <flux:error name="range.from" />
        <flux:error name="range.to" />
        <flux:error name="range.weekdays" />
    </x-card>

    <flux:radio.group wire:model.live="mode" variant="segmented" class="mb-3" aria-label="Jenis proses">
        <flux:radio value="isi" icon="squares-plus">Isi jadwal baru</flux:radio>
        <flux:radio value="ubah" icon="pencil-square">Ubah / hapus</flux:radio>
    </flux:radio.group>

    @if ($mode === 'isi')
        {{-- Langkah 2: waktu makan --}}
        <x-card class="mb-3">
            <p class="{{ $label }}">2 · Waktu makan yang dibuat</p>
            <p class="mt-0.5 text-xs text-hutan/60 dark:text-santan/60">Centang waktu makan, lalu atur vendor, jam ambil, porsi, dan menu.</p>
            <flux:error name="fillTypes" />
            <div class="mt-3 space-y-3">
                @foreach ($types as $type)
                    @php
                        $on = in_array($type->value, $fillTypes, true);
                    @endphp
                    <div wire:key="fill-{{ $type->value }}" @class(['rounded-xl border p-3', 'border-woka bg-woka-50/50 dark:bg-hutan-800/40' => $on, 'border-woka-100 dark:border-hutan-800' => ! $on])>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" value="{{ $type->value }}" wire:model.live="fillTypes" class="size-5 accent-woka">
                            <flux:icon :name="$type->icon()" class="size-4 text-woka dark:text-woka-light" />
                            <span class="font-semibold">{{ $type->label() }}</span>
                            @if ($type->isSnack())<span class="text-xs text-hutan/50 dark:text-santan/50">bila tersedia</span>@endif
                        </label>
                        @if ($on)
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <flux:select wire:model.live="meals.{{ $type->value }}.vendor_id" label="Vendor">
                                    @foreach ($vendors as $vendor)
                                        <flux:select.option :value="(string) $vendor->id">{{ $vendor->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <div class="grid grid-cols-2 gap-2">
                                    <flux:input type="time" wire:model="meals.{{ $type->value }}.start_time" label="Mulai ambil" />
                                    <flux:input type="time" wire:model="meals.{{ $type->value }}.end_time" label="Selesai" />
                                </div>
                                <div class="sm:col-span-2">
                                    <p class="mb-1 text-sm font-medium">Porsi dipesan</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="{{ $chip }}"><input type="radio" class="sr-only" value="auto" wire:model.live="meals.{{ $type->value }}.portions_mode"> Otomatis ({{ $recipients[$type->value] }} penerima)</label>
                                        <label class="{{ $chip }}"><input type="radio" class="sr-only" value="fixed" wire:model.live="meals.{{ $type->value }}.portions_mode"> Angka</label>
                                        @if (($meals[$type->value]['portions_mode'] ?? 'auto') === 'fixed')
                                            <flux:input type="number" min="0" wire:model="meals.{{ $type->value }}.portions" class="w-24" aria-label="Jumlah porsi {{ $type->label() }}" />
                                        @endif
                                    </div>
                                    <flux:error name="meals.{{ $type->value }}.portions" />
                                </div>
                                <div class="sm:col-span-2">
                                    <flux:textarea wire:model="meals.{{ $type->value }}.menu" rows="3" label="Menu (opsional)"
                                                   placeholder="Nasi kuning, telur, sambal&#10;Nasi, ikan woku, kangkung&#10;…"
                                                   description="Satu baris = menu satu hari, dipakai bergilir. Satu baris saja = menu sama setiap hari. Kosong = &quot;Menu menyusul&quot;." />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Langkah 3: jadwal yang sudah ada --}}
        <x-card class="mb-3">
            <p class="{{ $label }} mb-2">3 · Bila jadwal sudah ada</p>
            <flux:radio.group wire:model.live="existing">
                <flux:radio value="skip" label="Lewati" description="Jadwal yang sudah ada tidak diubah." />
                <flux:radio value="update" label="Perbarui dengan isian ini" description="Vendor, jam, porsi, dan menu diganti. Jadwal yang sudah ada penerimaannya tetap tidak diubah." />
            </flux:radio.group>
        </x-card>

        <div class="sticky bottom-0 z-20 -mx-4 mt-4 border-t border-woka-100 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border dark:border-hutan-800 dark:bg-hutan-900/95">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm" data-test="fill-plan">
                    <strong>{{ $plan['create'] }}</strong> dibuat · <strong>{{ $plan['update'] }}</strong> diperbarui · <strong>{{ $plan['skip'] }}</strong> dilewati
                </p>
                <x-confirm-button action="runFill" :label="'Proses '.$plan['total'].' jadwal'" icon="bolt" variant="primary" size="base" class="w-full sm:w-auto"
                                  title="Isi jadwal makan sekaligus?" :message="$plan['create'].' jadwal baru dibuat, '.$plan['update'].' diperbarui, '.$plan['skip'].' dilewati.'" confirmLabel="Proses" />
            </div>
        </div>
    @else
        {{-- Langkah 2: pilih jadwal --}}
        <x-card class="mb-3">
            <p class="{{ $label }} mb-2">2 · Jadwal yang diubah</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($types as $type)
                    <label class="{{ $chip }}" wire:key="ft-{{ $type->value }}">
                        <input type="checkbox" class="sr-only" value="{{ $type->value }}" wire:model.live="edit.types"> {{ $type->label() }}
                    </label>
                @endforeach
            </div>
            <flux:error name="edit.types" />
            <div class="mt-3">
                <flux:select wire:model.live="edit.vendor_filter" label="Vendor">
                    <flux:select.option value="">Semua vendor</flux:select.option>
                    @foreach ($allVendors as $vendor)
                        <flux:select.option :value="(string) $vendor->id">{{ $vendor->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <p class="mt-3 text-sm" data-test="matched-count">
                <strong>{{ $matched->count() }}</strong> jadwal cocok
                @if ($locked) · <span class="text-kunyit-700 dark:text-kunyit">{{ $locked }} sudah ada penerimaan (tidak diubah)</span>@endif
            </p>
            @if ($matched->isNotEmpty())
                <details class="mt-2 text-sm">
                    <summary class="cursor-pointer font-semibold text-woka dark:text-woka-light">Lihat daftar</summary>
                    <ul class="mt-2 max-h-72 divide-y divide-woka-100 overflow-y-auto dark:divide-hutan-800">
                        @foreach ($matched->take(100) as $schedule)
                            <li class="flex items-start justify-between gap-2 py-1.5" wire:key="m-{{ $schedule->id }}">
                                <span class="min-w-0">
                                    <span class="font-semibold">{{ tanggal($schedule->date, 'D, j M') }} · {{ $schedule->meal_type->label() }}</span>
                                    <span class="block truncate text-xs text-hutan/60 dark:text-santan/60">{{ $schedule->vendor?->name }} · {{ $schedule->windowLabel() }} · {{ $schedule->portions_ordered }} porsi · {{ $schedule->menu }}</span>
                                </span>
                                @if ($schedule->receipts_count > 0)<flux:badge size="sm" color="amber">{{ $schedule->receipts_count }} ttd</flux:badge>@endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </x-card>

        {{-- Langkah 3: perubahan --}}
        <x-card class="mb-3">
            <p class="{{ $label }}">3 · Perubahan</p>
            <p class="mt-0.5 text-xs text-hutan/60 dark:text-santan/60">Centang yang ingin diubah; bagian lain tetap seperti semula.</p>
            <flux:error name="edit.changes" />
            @php
                $changes = $edit['changes'] ?? [];
            @endphp
            <div class="mt-3 space-y-3">
                <div class="rounded-xl border border-woka-100 p-3 dark:border-hutan-800">
                    <label class="flex items-center gap-2 font-semibold"><input type="checkbox" value="vendor" wire:model.live="edit.changes" class="size-5 accent-woka"> Ganti vendor</label>
                    @if (in_array('vendor', $changes, true))
                        <flux:select wire:model="edit.vendor_id" class="mt-2" aria-label="Vendor baru">
                            @foreach ($vendors as $vendor)
                                <flux:select.option :value="(string) $vendor->id">{{ $vendor->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="edit.vendor_id" />
                    @endif
                </div>

                <div class="rounded-xl border border-woka-100 p-3 dark:border-hutan-800">
                    <label class="flex items-center gap-2 font-semibold"><input type="checkbox" value="time" wire:model.live="edit.changes" class="size-5 accent-woka"> Jam ambil</label>
                    @if (in_array('time', $changes, true))
                        <div class="mt-2 flex flex-wrap gap-2">
                            <label class="{{ $chip }}"><input type="radio" class="sr-only" value="shift" wire:model.live="edit.time_mode"> Geser jam</label>
                            <label class="{{ $chip }}"><input type="radio" class="sr-only" value="set" wire:model.live="edit.time_mode"> Atur jam</label>
                        </div>
                        @if (($edit['time_mode'] ?? 'shift') === 'shift')
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <flux:input type="number" step="5" min="-240" max="240" wire:model="edit.shift_minutes" class="w-28" aria-label="Geser menit" />
                                <span class="text-sm">menit (negatif = lebih awal)</span>
                            </div>
                            <flux:error name="edit.shift_minutes" />
                        @else
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <flux:input type="time" wire:model="edit.start_time" label="Mulai ambil" />
                                <flux:input type="time" wire:model="edit.end_time" label="Selesai" />
                            </div>
                            @if (count($edit['types'] ?? []) > 1)
                                <p class="mt-1 text-xs text-kunyit-700 dark:text-kunyit">Jam yang sama dipakai untuk semua waktu makan terpilih. Untuk banyak waktu makan, pakai Geser jam.</p>
                            @endif
                        @endif
                    @endif
                </div>

                <div class="rounded-xl border border-woka-100 p-3 dark:border-hutan-800">
                    <label class="flex items-center gap-2 font-semibold"><input type="checkbox" value="portions" wire:model.live="edit.changes" class="size-5 accent-woka"> Porsi dipesan</label>
                    @if (in_array('portions', $changes, true))
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <label class="{{ $chip }}"><input type="radio" class="sr-only" value="auto" wire:model.live="edit.portions_mode"> Sesuai jumlah penerima</label>
                            <label class="{{ $chip }}"><input type="radio" class="sr-only" value="fixed" wire:model.live="edit.portions_mode"> Angka</label>
                            @if (($edit['portions_mode'] ?? 'auto') === 'fixed')
                                <flux:input type="number" min="0" wire:model="edit.portions" class="w-24" aria-label="Jumlah porsi" />
                            @endif
                        </div>
                        <flux:error name="edit.portions" />
                    @endif
                </div>

                <div class="rounded-xl border border-woka-100 p-3 dark:border-hutan-800">
                    <label class="flex items-center gap-2 font-semibold"><input type="checkbox" value="menu" wire:model.live="edit.changes" class="size-5 accent-woka"> Menu</label>
                    @if (in_array('menu', $changes, true))
                        <flux:textarea wire:model="edit.menu" rows="3" class="mt-2" placeholder="Satu baris = menu satu hari (bergilir per waktu makan)" aria-label="Menu baru" />
                        <flux:error name="edit.menu" />
                    @endif
                </div>
            </div>
        </x-card>

        <div class="sticky bottom-0 z-20 -mx-4 mt-4 border-t border-woka-100 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border dark:border-hutan-800 dark:bg-hutan-900/95">
            <div class="grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                <x-confirm-button action="runDelete" :label="'Hapus '.($matched->count() - $locked)" icon="trash" variant="danger" size="base"
                                  title="Hapus jadwal terpilih?" :message="($matched->count() - $locked).' jadwal tanpa penerimaan akan dihapus. Jadwal yang sudah ada penerimaannya tetap disimpan.'" confirmLabel="Ya, hapus" />
                <x-confirm-button action="runChange" :label="'Ubah '.($matched->count() - $locked).' jadwal'" icon="check" variant="primary" size="base"
                                  title="Terapkan perubahan?" :message="'Perubahan diterapkan ke '.($matched->count() - $locked).' jadwal tanpa penerimaan.'" confirmLabel="Terapkan" />
            </div>
        </div>
    @endif
</div>
