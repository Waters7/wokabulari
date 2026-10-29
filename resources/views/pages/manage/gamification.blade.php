<?php

use App\Enums\Role;
use App\Http\Requests\GamificationRuleRequest;
use App\Models\Badge;
use App\Models\GamificationRule;
use App\Models\Level;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Gamification\PointService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gamifikasi')] class extends Component {
    /** @var array<int, array{name: string, min_points: int}> */
    public array $levels = [];

    public bool $showAdjust = false;

    /** @var array<string, mixed> */
    public array $adjust = ['user_id' => null, 'points' => 10, 'reason' => ''];

    public function mount(): void
    {
        $this->authorize('viewAny', GamificationRule::class);
        $this->levels = Level::query()->orderBy('min_points')->get()
            ->mapWithKeys(fn (Level $l) => [$l->id => ['name' => $l->name, 'min_points' => $l->min_points]])->all();
    }

    public function saveLevels(): void
    {
        $this->authorize('viewAny', GamificationRule::class);
        $validator = Validator::make(['levels' => $this->levels], GamificationRuleRequest::levelRules(), [], ['levels.*.name' => 'nama level', 'levels.*.min_points' => 'poin minimal']);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->messages());
        }

        DB::transaction(function () {
            // Geser sementara agar unique(min_points) tidak bentrok saat ditukar.
            Level::query()->update(['min_points' => DB::raw('min_points + 1000000')]);
            foreach ($this->levels as $id => $level) {
                Level::query()->whereKey($id)->update(['name' => trim($level['name']), 'min_points' => (int) $level['min_points']]);
            }
        });

        Flux::toast(variant: 'success', text: 'Level disimpan.');
    }

    public function toggleBadge(int $id): void
    {
        $this->authorize('viewAny', GamificationRule::class);
        $badge = Badge::query()->findOrFail($id);
        $badge->update(['is_active' => ! $badge->is_active]);
    }

    public function openAdjust(): void
    {
        $this->authorize('create', PointTransaction::class);
        $this->resetErrorBag();
        $this->adjust = ['user_id' => null, 'points' => 10, 'reason' => ''];
        $this->showAdjust = true;
    }

    public function saveAdjust(PointService $points): void
    {
        $this->authorize('create', PointTransaction::class);
        $validator = Validator::make($this->adjust, GamificationRuleRequest::adjustmentRules(), [], ['user_id' => 'peserta', 'points' => 'poin', 'reason' => 'alasan']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['adjust.'.$k => $m])->all());
        }

        $participant = User::query()->findOrFail($this->adjust['user_id']);
        abort_unless($participant->isParticipant(), 422);
        $points->adjust($participant, (int) $this->adjust['points'], $this->adjust['reason'], auth()->user());

        $this->showAdjust = false;
        Flux::toast(variant: 'success', text: 'Poin '.$participant->name.' disesuaikan.');
    }

    public function with(): array
    {
        return [
            'badges' => Badge::query()->withCount('users')->orderBy('id')->get(),
            'participants' => $this->showAdjust ? User::query()->role(Role::Participant->value)->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'recentManual' => PointTransaction::query()->with(['user', 'creator'])->where('event_key', 'manual')->latest()->limit(5)->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Gamifikasi" subtitle="Atur level dan lencana, serta penyesuaian poin manual. Nilai poin diatur di Komponen poin.">
        <x-slot:actions>
            <flux:button icon="trophy" :href="route('leaderboard')" wire:navigate>Peringkat</flux:button>
            <flux:button variant="primary" icon="plus-circle" wire:click="openAdjust">Sesuaikan poin</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-card :href="route('master.point-components')" class="mb-6 flex items-center gap-3">
        <flux:icon.calculator class="size-6 shrink-0 text-woka" />
        <span class="min-w-0 flex-1">
            <span class="block font-semibold">Komponen poin</span>
            <span class="block text-xs text-hutan/60 dark:text-santan/60">Poin absen, sesi, tanda terima konsumsi, asrama, tugas, quiz, dan komponen tambahan dari pengajar.</span>
        </span>
        <flux:icon.chevron-right class="size-4 shrink-0 text-hutan/40" />
    </x-card>

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Level</h2>
    <x-card class="mb-6">
        <form wire:submit="saveLevels" class="space-y-2">
            @foreach ($levels as $id => $level)
                <div class="flex items-end gap-2" wire:key="lv-{{ $id }}">
                    <flux:input wire:model="levels.{{ $id }}.name" size="sm" label="Nama" class="flex-1" />
                    <flux:input wire:model="levels.{{ $id }}.min_points" type="number" size="sm" label="Min. poin" class="w-28" />
                </div>
            @endforeach
            <flux:error name="levels.*.min_points" />
            <flux:button type="submit" size="sm" variant="primary">Simpan level</flux:button>
        </form>
    </x-card>

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Lencana</h2>
    <div class="mb-6 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($badges as $badge)
            <x-card wire:key="badge-{{ $badge->id }}">
                <div class="flex items-start gap-3">
                    <flux:icon :name="$badge->icon ?? 'trophy'" class="size-7 shrink-0 text-kunyit-700 dark:text-kunyit" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $badge->name }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $badge->description }}</p>
                        <p class="mt-1 text-xs">{{ $badge->users_count }} peserta meraih</p>
                    </div>
                    <flux:button size="xs" :variant="$badge->is_active ? 'primary' : 'filled'" wire:click="toggleBadge({{ $badge->id }})">{{ $badge->is_active ? 'Aktif' : 'Nonaktif' }}</flux:button>
                </div>
            </x-card>
        @endforeach
    </div>

    @if ($recentManual->isNotEmpty())
        <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Penyesuaian terakhir</h2>
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($recentManual as $tx)
                <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="m-{{ $tx->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $tx->user?->name }}</p>
                        <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ $tx->description }} · {{ $tx->creator?->name }} · {{ tanggal_jam($tx->created_at) }}</p>
                    </div>
                    <span @class(['shrink-0 font-bold', 'text-woka' => $tx->points > 0, 'text-rica' => $tx->points < 0])>{{ $tx->points > 0 ? '+' : '' }}{{ $tx->points }}</span>
                </div>
            @endforeach
        </x-card>
    @endif

    <flux:modal wire:model.self="showAdjust" class="w-full max-w-sm">
        <form wire:submit="saveAdjust" class="space-y-4">
            <flux:heading size="lg">Sesuaikan poin</flux:heading>
            <flux:select wire:model="adjust.user_id" label="Peserta" placeholder="Pilih peserta">
                @foreach ($participants as $p)
                    <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="adjust.points" type="number" label="Poin (negatif untuk mengurangi)" />
            <flux:input wire:model="adjust.reason" label="Alasan" placeholder="Juara lomba pidato bahasa Jepang" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
