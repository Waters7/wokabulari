<?php

use App\Actions\Meals\ReceiveMeal;
use App\Enums\SpecialMealStatus;
use App\Models\MealReceipt;
use App\Models\MealSchedule;
use App\Models\SpecialMealRequest;
use App\Models\User;
use App\Services\MealRoster;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Monitor makan')] class extends Component {
    #[Url]
    public string $date = '';

    #[Url]
    public ?int $scheduleId = null;

    public string $search = '';

    public bool $showProxy = false;

    public ?int $proxyUserId = null;

    public string $proxyNote = '';

    public function mount(): void
    {
        $this->authorize('viewAny', MealSchedule::class);
        $this->date = $this->date ?: today()->toDateString();
    }

    public function updatedDate(): void
    {
        $this->scheduleId = null;
    }

    /** Jadwal terpilih; bawaan: yang sedang dibuka, lalu yang terdekat. */
    protected function schedule($schedules): ?MealSchedule
    {
        return $schedules->firstWhere('id', $this->scheduleId)
            ?? $schedules->first(fn ($s) => $s->isOpen())
            ?? $schedules->first(fn ($s) => now()->lt($s->ends_at))
            ?? $schedules->last();
    }

    public function openProxy(int $userId): void
    {
        $this->authorize('proxy', MealSchedule::class);
        $this->resetErrorBag();
        $this->proxyUserId = $userId;
        $this->proxyNote = '';
        $this->showProxy = true;
    }

    public function proxyReceive(int $scheduleId, int $userId, string $signature, ReceiveMeal $receive): void
    {
        $schedule = MealSchedule::query()->findOrFail($scheduleId);
        $this->authorize('proxy', $schedule);
        $this->resetErrorBag();

        if (blank($this->proxyNote)) {
            $this->addError('proxyNote', 'Catatan wajib diisi (mis. sakit di kamar, diantar petugas).');

            return;
        }

        try {
            $receive->handle($schedule, User::query()->findOrFail($userId), $signature, auth()->user(), $this->proxyNote);
        } catch (\InvalidArgumentException $e) {
            $this->addError('proxyNote', $e->getMessage());

            return;
        }

        $this->showProxy = false;
        Flux::toast(variant: 'success', text: 'Penerimaan diwakilkan tercatat.');
    }

    public function advanceSpecial(int $id): void
    {
        $this->authorize('proxy', MealSchedule::class);
        $request = SpecialMealRequest::query()->findOrFail($id);
        $next = match ($request->status) {
            SpecialMealStatus::Pending => SpecialMealStatus::Prepared,
            default => SpecialMealStatus::Delivered,
        };
        $request->update(['status' => $next, 'handled_by' => auth()->id()]);
        Flux::toast(text: 'Status makanan khusus: '.$next->label().'.');
    }

    public function with(MealRoster $roster): array
    {
        $date = CarbonImmutable::parse($this->date);
        $schedules = MealSchedule::query()->with(['vendor', 'delivery'])->onDate($date)->orderBy('starts_at')->get();
        $schedule = $this->schedule($schedules);
        // Total mengikuti jenis konsumsi (peserta tidak asrama hanya untuk jenis tertentu).
        $participants = $roster->participants($schedule?->meal_type);
        $receipts = $schedule
            ? MealReceipt::query()->with('proxy')->where('meal_schedule_id', $schedule->id)->latest('received_at')->get()->keyBy('user_id')
            : collect();
        $flags = $roster->flags($date, $participants->pluck('id'));

        $pending = $participants->reject(fn ($p) => $receipts->has($p->id))
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->name), mb_strtolower($this->search))))
            // Yang punya penanda (izin, sakit, keluar) tampil paling atas.
            ->sortBy(fn ($p) => [isset($flags[$p->id]) ? 0 : 1, $p->name])->values();

        return [
            'schedules' => $schedules,
            'schedule' => $schedule,
            'total' => $participants->count(),
            'receivedCount' => $receipts->count(),
            'proxyCount' => $receipts->whereNotNull('proxy_by')->count(),
            'flaggedCount' => count(array_intersect_key($flags, $participants->keyBy('id')->all())),
            'pending' => $pending,
            'recent' => $receipts->take(8)->map(fn ($r) => $r->setRelation('user', $participants->firstWhere('id', $r->user_id) ?? $r->user)),
            'flags' => $flags,
            'specials' => SpecialMealRequest::query()->with(['user.currentRoomAssignment.room.dormitory'])->whereDate('date', $date)
                ->orderByRaw("case status when 'pending' then 0 when 'prepared' then 1 else 2 end")->get(),
            'proxyUser' => $this->proxyUserId ? $participants->firstWhere('id', $this->proxyUserId) : null,
            'canProxy' => auth()->user()->can('proxy', MealSchedule::class),
        ];
    }
}; ?>

<div wire:poll.5s>
    <x-page-header title="Monitor makan" subtitle="Porsi diterima vs total peserta, diperbarui otomatis.">
        <x-slot:actions>
            <flux:input type="date" wire:model.live="date" size="sm" class="max-w-40" aria-label="Tanggal" />
        </x-slot:actions>
    </x-page-header>

    @if ($schedules->isEmpty())
        <x-empty-state icon="calendar-days" title="Belum ada jadwal makan" description="Atur jadwal makan untuk tanggal ini terlebih dulu.">
            @can('meal.manage')
                <flux:button :href="route('meals.schedules')" wire:navigate variant="primary" class="mt-4">Buka jadwal makan</flux:button>
            @endcan
        </x-empty-state>
    @else
        <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1">
            @foreach ($schedules as $item)
                <button type="button" wire:click="$set('scheduleId', {{ $item->id }})" wire:key="tab-{{ $item->id }}"
                        @class(['flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition',
                            'bg-woka text-white ring-woka' => $schedule?->id === $item->id,
                            'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $schedule?->id !== $item->id])>
                    <flux:icon :name="$item->meal_type->icon()" class="size-4" />
                    {{ $item->meal_type->label() }}
                    @if ($schedules->where('meal_type', $item->meal_type)->count() > 1)
                        <span class="font-normal opacity-80">· {{ $item->vendor->name }}</span>
                    @endif
                    @if ($item->isOpen())
                        <span class="size-2 animate-pulse rounded-full bg-kunyit"></span>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($schedule)
            <x-card class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="whitespace-pre-line font-semibold">{{ $schedule->menu }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $schedule->vendor->name }} · {{ $schedule->windowLabel() }} WITA · dipesan {{ $schedule->portions_ordered }} porsi</p>
                        @if ($delivery = $schedule->delivery)
                            <p class="mt-1 text-sm" data-test="delivery-info">
                                <span class="font-semibold text-woka dark:text-woka-light">Diterima {{ $delivery->quantityLabel() }}</span>
                                · siap {{ jam($delivery->ready_at) }} · {{ $delivery->location }}
                                @if ($delivery->notified_at) · peserta diberi tahu {{ jam($delivery->notified_at) }} @endif
                            </p>
                            <p class="mt-1 flex gap-3 text-xs">
                                @if ($delivery->menu_photo_path)<a href="{{ $delivery->photoUrl('menu') }}" target="_blank" class="font-semibold text-woka underline">Foto menu</a>@endif
                                @if ($delivery->total_photo_path)<a href="{{ $delivery->photoUrl('total') }}" target="_blank" class="font-semibold text-woka underline">Foto total</a>@endif
                            </p>
                        @endif
                    </div>
                    @if ($schedule->isOpen())
                        <flux:badge color="amber" icon="signal">Sedang dibuka</flux:badge>
                    @elseif (now()->lt($schedule->starts_at))
                        <flux:badge>Dibuka {{ jam($schedule->starts_at) }}</flux:badge>
                    @else
                        <flux:badge color="zinc">Ditutup</flux:badge>
                    @endif
                </div>
                @php($percent = $total ? round($receivedCount / $total * 100) : 0)
                <div class="mt-4">
                    <div class="flex items-end justify-between">
                        <p class="font-display text-4xl font-bold text-woka">{{ $receivedCount }}<span class="text-xl text-hutan/50 dark:text-santan/50">/{{ $total }}</span></p>
                        <p class="text-sm font-semibold">{{ $percent }}%</p>
                    </div>
                    <div class="mt-2 h-3 overflow-hidden rounded-full bg-woka-50 dark:bg-hutan-800">
                        <div class="h-full rounded-full bg-woka transition-all" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-xl bg-santan p-2 dark:bg-hutan-800"><p class="text-lg font-bold">{{ $total - $receivedCount }}</p>belum ambil</div>
                        <div class="rounded-xl bg-santan p-2 dark:bg-hutan-800"><p class="text-lg font-bold">{{ $proxyCount }}</p>diwakilkan</div>
                        <div class="rounded-xl bg-santan p-2 dark:bg-hutan-800"><p class="text-lg font-bold">{{ $flaggedCount }}</p>izin/sakit</div>
                    </div>
                </div>
            </x-card>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="min-w-0 lg:col-span-2">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Belum mengambil ({{ $pending->count() }})</h2>
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" size="sm" placeholder="Cari nama" class="max-w-40" />
                    </div>
                    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
                        @forelse ($pending as $participant)
                            <div class="flex items-center gap-3 px-4 py-3" wire:key="p-{{ $participant->id }}">
                                <flux:avatar size="sm" :name="$participant->name" :initials="$participant->initials()" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ $participant->name }}</p>
                                    <div class="flex flex-wrap items-center gap-1 text-xs text-hutan/60 dark:text-santan/60">
                                        @if ($room = $participant->currentRoomAssignment?->room)
                                            <span>{{ $room->dormitory?->name }} · {{ $room->number }}</span>
                                        @endif
                                        @foreach ($flags[$participant->id] ?? [] as $flag)
                                            <flux:badge size="sm" color="sky">{{ $flag }}</flux:badge>
                                        @endforeach
                                        @if ($participant->profile?->hasAllergies())
                                            <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ $participant->profile->allergies }}</flux:badge>
                                        @endif
                                    </div>
                                </div>
                                @if ($canProxy)
                                    <flux:button size="sm" icon="pencil" wire:click="openProxy({{ $participant->id }})">Wakilkan</flux:button>
                                @endif
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Semua peserta so ambil makanan. Mantap!</p>
                        @endforelse
                    </x-card>
                </div>

                <div class="min-w-0 space-y-4">
                    <x-card title="Makanan khusus" subtitle="Dari laporan sakit / permintaan petugas">
                        @forelse ($specials as $special)
                            <div class="border-t border-woka-100 py-2 first:border-0 first:pt-0 dark:border-hutan-800" wire:key="sp-{{ $special->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 text-sm">
                                        <p class="font-semibold">{{ $special->user?->name }}</p>
                                        <p>{{ $special->request }}</p>
                                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $special->meal_type?->label() ?? 'Sepanjang hari' }}@if ($special->delivered_to_room) · antar ke kamar {{ $special->user?->currentRoomAssignment?->room?->number }}@endif</p>
                                    </div>
                                    <x-status-badge :status="$special->status" />
                                </div>
                                @if ($canProxy && $special->status !== \App\Enums\SpecialMealStatus::Delivered)
                                    <flux:button size="xs" class="mt-1" wire:click="advanceSpecial({{ $special->id }})">
                                        {{ $special->status === \App\Enums\SpecialMealStatus::Pending ? 'Tandai disiapkan' : 'Tandai diantar' }}
                                    </flux:button>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-hutan/60 dark:text-santan/60">Tidak ada permintaan makanan khusus.</p>
                        @endforelse
                    </x-card>

                    <x-card title="Baru diterima">
                        @forelse ($recent as $receipt)
                            <div class="flex items-center justify-between gap-2 py-1.5 text-sm" wire:key="r-{{ $receipt->id }}">
                                <p class="min-w-0 truncate">{{ $receipt->user?->name }}@if ($receipt->isProxy()) <span class="text-xs text-hutan/60 dark:text-santan/60">(diwakilkan)</span>@endif</p>
                                <a href="{{ $receipt->signatureUrl() }}" target="_blank" class="shrink-0 text-xs font-semibold text-woka underline">{{ jam($receipt->received_at) }}</a>
                            </div>
                        @empty
                            <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada penerimaan.</p>
                        @endforelse
                    </x-card>
                </div>
            </div>
        @endif
    @endif

    <flux:modal wire:model.self="showProxy" class="w-full max-w-md">
        @if ($proxyUser && $schedule)
            <div class="space-y-4" wire:key="proxy-{{ $proxyUser->id }}-{{ $schedule->id }}">
                <div>
                    <flux:heading size="lg">Penerimaan diwakilkan</flux:heading>
                    <flux:text>{{ $schedule->meal_type->label() }} untuk <strong>{{ $proxyUser->name }}</strong>. Tanda tangan petugas sebagai bukti.</flux:text>
                </div>
                <flux:textarea wire:model="proxyNote" label="Catatan" rows="2" placeholder="Sakit di kamar, makanan diantar petugas" />
                <x-signature-pad action="proxyReceive" :args="[$schedule->id, $proxyUser->id]" submit-label="Simpan penerimaan" height="h-40" />
            </div>
        @endif
    </flux:modal>
</div>
