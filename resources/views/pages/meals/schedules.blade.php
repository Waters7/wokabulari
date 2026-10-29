<?php

use App\Actions\Meals\ManageMealSchedules;
use App\Actions\Meals\SetNonResidentMealTypes;
use App\Enums\MealType;
use App\Http\Requests\MealScheduleRequest;
use App\Http\Requests\NonResidentMealsRequest;
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

new #[Title('Jadwal makan')] class extends Component {
    #[Url]
    public string $week = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showNonResident = false;

    /** @var list<string> Jenis konsumsi peserta tidak asrama */
    public array $nonResidentTypes = [];

    public function mount(MealRoster $roster): void
    {
        $this->week = $this->week ?: now()->startOfWeek()->toDateString();
        $this->resetForm();
        $this->nonResidentTypes = array_map(fn (MealType $type) => $type->value, $roster->nonResidentTypes());
    }

    public function saveNonResident(SetNonResidentMealTypes $setTypes): void
    {
        abort_unless(auth()->user()->can('meal.manage'), 403);
        $this->resetErrorBag('nonResidentTypes');

        $validator = Validator::make(['types' => $this->nonResidentTypes], NonResidentMealsRequest::rulesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(['nonResidentTypes' => $validator->errors()->all()]);
        }

        $setTypes->handle($validator->validated()['types'], auth()->user());
        $this->showNonResident = false;
        Flux::toast(variant: 'success', text: 'Konsumsi peserta tidak asrama tersimpan.');
    }

    protected function resetForm(?string $date = null, ?string $type = null): void
    {
        $mealType = MealType::tryFrom((string) $type) ?? MealType::Breakfast;
        [$start, $end] = $mealType->defaultWindow();

        $this->form = [
            'date' => $date ?? today()->toDateString(),
            'meal_type' => $mealType->value,
            'vendor_id' => Vendor::query()->active()->value('id'),
            'menu' => '',
            'start_time' => $start,
            'end_time' => $end,
            'portions_ordered' => app(MealRoster::class)->participants($mealType)->count(),
        ];
    }

    public function updatedFormMealType(string $value): void
    {
        $type = MealType::tryFrom($value) ?? MealType::Breakfast;
        [$this->form['start_time'], $this->form['end_time']] = $type->defaultWindow();

        // Jadwal baru: porsi bawaan mengikuti jumlah penerima jenis ini.
        if (! $this->editingId) {
            $this->form['portions_ordered'] = app(MealRoster::class)->participants($type)->count();
        }
    }

    public function shift(int $weeks): void
    {
        $this->week = CarbonImmutable::parse($this->week)->addWeeks($weeks)->startOfWeek()->toDateString();
    }

    public function create(?string $date = null, ?string $type = null): void
    {
        $this->authorize('create', MealSchedule::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm($date, $type);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $schedule = MealSchedule::findOrFail($id);
        $this->authorize('update', $schedule);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = [
            'date' => $schedule->date->toDateString(),
            'meal_type' => $schedule->meal_type->value,
            'vendor_id' => $schedule->vendor_id,
            'menu' => $schedule->menu,
            'start_time' => $schedule->starts_at->format('H:i'),
            'end_time' => $schedule->ends_at->format('H:i'),
            'portions_ordered' => $schedule->portions_ordered,
        ];
        $this->showForm = true;
    }

    public function save(ManageMealSchedules $manage): void
    {
        $schedule = $this->editingId ? MealSchedule::findOrFail($this->editingId) : null;
        $this->authorize($schedule ? 'update' : 'create', $schedule ?? MealSchedule::class);
        $this->resetErrorBag();

        $validator = Validator::make($this->form, MealScheduleRequest::rulesFor(), [], ['meal_type' => 'waktu makan', 'vendor_id' => 'vendor', 'start_time' => 'jam mulai', 'end_time' => 'jam selesai', 'portions_ordered' => 'porsi dipesan']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        try {
            $manage->save($validator->validated(), auth()->user(), $schedule);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.vendor_id', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Jadwal makan tersimpan.');
    }

    public function delete(int $id): void
    {
        $schedule = MealSchedule::findOrFail($id);

        // Super admin melewati policy, jadi cek penerimaan tetap eksplisit.
        if (! auth()->user()->can('delete', $schedule) || $schedule->receipts()->exists()) {
            Flux::toast(variant: 'danger', text: 'Jadwal yang sudah ada penerimaannya tidak bisa dihapus.');

            return;
        }

        $schedule->delete();
        Flux::toast(text: 'Jadwal dihapus.');
    }

    public function duplicate(ManageMealSchedules $manage): void
    {
        $this->authorize('create', MealSchedule::class);
        $count = $manage->duplicateWeek(CarbonImmutable::parse($this->week), auth()->user());
        Flux::toast(variant: 'success', text: "{$count} jadwal disalin ke minggu berikutnya.");
        $this->shift(1);
    }

    public function with(): array
    {
        $start = CarbonImmutable::parse($this->week)->startOfWeek();

        return [
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn ($i) => $start->addDays($i)),
            'schedules' => MealSchedule::query()->with('vendor')->withCount('receipts')
                ->whereDateBetween('date', $start->toDateString(), $start->endOfWeek()->toDateString())
                ->orderBy('starts_at')->get()->groupBy(fn ($s) => $s->date->toDateString()),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(),
            'types' => MealType::cases(),
            'nonResidentCount' => app(MealRoster::class)->participants()->filter(fn ($p) => $p->profile?->lives_in_dorm === false)->count(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Jadwal makan" subtitle="Sarapan, snack, makan siang, dan makan malam per hari beserta vendor dan rentang pengambilan.">
        <x-slot:actions>
            @can('meal.manage')
                <flux:button icon="adjustments-horizontal" wire:click="$set('showNonResident', true)">Peserta tidak asrama</flux:button>
            @endcan
            <x-confirm-button action="duplicate" label="Salin ke minggu depan" icon="document-duplicate" variant="filled" size="base"
                              title="Salin jadwal minggu ini?" message="Semua jadwal minggu ini disalin ke minggu berikutnya. Jadwal yang sudah ada tidak ditimpa." confirmLabel="Salin" />
            <flux:button variant="primary" icon="plus" wire:click="create">Jadwal baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex items-center gap-2">
        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="shift(-1)" aria-label="Minggu sebelumnya" />
        <p class="font-display text-lg font-bold">{{ tanggal($start, 'j M') }} – {{ tanggal($start->endOfWeek()) }}</p>
        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="shift(1)" aria-label="Minggu berikutnya" />
    </div>

    <div class="space-y-4">
        @foreach ($days as $day)
            @php($daySchedules = $schedules[$day->toDateString()] ?? collect())
            <section wire:key="md-{{ $day->toDateString() }}">
                <h2 @class(['mb-2 text-sm font-bold uppercase tracking-wide', 'text-woka' => $day->isToday(), 'text-hutan/60 dark:text-santan/60' => ! $day->isToday()])>{{ tanggal($day, 'l, j M') }}</h2>
                <div class="grid gap-2 sm:grid-cols-3 xl:grid-cols-5">
                    @foreach ($types as $type)
                        @php($items = $daySchedules->where('meal_type', $type))
                        @forelse ($items as $schedule)
                            <x-card wire:key="ms-{{ $schedule->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <x-status-badge :status="$schedule->meal_type" />
                                        <p class="mt-1 text-sm font-semibold">{{ $schedule->vendor->name }}</p>
                                        <p class="line-clamp-2 text-sm">{{ $schedule->menu }}</p>
                                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $schedule->windowLabel() }} · {{ $schedule->receipts_count }}/{{ $schedule->portions_ordered }} porsi</p>
                                    </div>
                                    <div class="flex shrink-0 flex-col">
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $schedule->id }})" aria-label="Ubah" />
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $schedule->id }})" wire:confirm="Hapus jadwal ini?" aria-label="Hapus" />
                                    </div>
                                </div>
                            </x-card>
                        @empty
                            <button type="button" wire:click="create('{{ $day->toDateString() }}', '{{ $type->value }}')"
                                    class="flex min-h-11 items-center justify-center gap-1 rounded-2xl border border-dashed border-woka-200 text-sm text-hutan/50 sm:min-h-20 dark:text-santan/50 hover:bg-woka-50 dark:border-hutan-800">
                                <flux:icon.plus class="size-4" /> {{ $type->label() }}
                            </button>
                        @endforelse
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @can('meal.manage')
        <flux:modal wire:model.self="showNonResident" class="w-full max-w-md">
            <form wire:submit="saveNonResident" class="space-y-4">
                <div>
                    <flux:heading size="lg">Konsumsi peserta tidak asrama</flux:heading>
                    <flux:text class="mt-1">
                        {{ $nonResidentCount }} peserta terdaftar tidak tinggal di asrama. Centang konsumsi yang tetap mereka terima;
                        jenis lain tidak dihitung di porsi, monitor, maupun daftar konsumsi mereka.
                    </flux:text>
                </div>
                <flux:checkbox.group wire:model="nonResidentTypes" label="Konsumsi yang diterima">
                    @foreach ($types as $type)
                        <flux:checkbox :value="$type->value" :label="$type->label()" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="nonResidentTypes" />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah jadwal makan' : 'Jadwal makan baru' }}</flux:heading>
            <div class="grid grid-cols-2 gap-3">
                <flux:input wire:model="form.date" type="date" label="Tanggal" />
                <flux:select wire:model.live="form.meal_type" label="Waktu makan">
                    @foreach ($types as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:select wire:model="form.vendor_id" label="Vendor">
                @foreach ($vendors as $vendor)
                    <flux:select.option :value="$vendor->id">{{ $vendor->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="form.menu" label="Menu" rows="2" placeholder="Nasi, ikan woku, sayur kangkung, pisang" />
            <div class="grid grid-cols-3 gap-3">
                <flux:input wire:model="form.start_time" type="time" label="Mulai ambil" />
                <flux:input wire:model="form.end_time" type="time" label="Selesai" />
                <flux:input wire:model="form.portions_ordered" type="number" min="0" label="Porsi" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
