<?php

use App\Models\Badge;
use App\Models\PointTransaction;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\PointService;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Poin & lencana')] class extends Component {
    use WithPagination;

    public bool $anonymous = false;

    /** Lencana yang sedang dibagikan (dari tombol Bagikan atau notifikasi lencana baru). */
    #[Url(as: 'bagikan')]
    public ?int $shareId = null;

    public bool $showShare = false;

    /** Riwayat: semua, penambahan, atau pengurangan (punishment). */
    #[Url(as: 'riwayat')]
    public string $kind = 'all';

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->authorize('viewAny', PointTransaction::class);
        $this->anonymous = (bool) auth()->user()->leaderboard_anonymous;
        $this->share($this->shareId);
    }

    /** Hanya lencana yang sudah diraih sendiri yang bisa dibagikan. */
    public function share(?int $badgeId): void
    {
        $owned = $badgeId && auth()->user()->badges()->whereKey($badgeId)->exists();
        $this->shareId = $owned ? $badgeId : null;
        $this->showShare = $owned;
    }

    public function updatedShowShare(bool $value): void
    {
        if (! $value) {
            $this->shareId = null;
        }
    }

    /** Isi kartu bagikan: tanpa data sensitif, hanya nama (opsional), kelas, dan lencana. */
    protected function shareCard(?Badge $badge, int $total, LevelService $levels): ?array
    {
        if (! $badge) {
            return null;
        }

        $user = auth()->user();
        $level = $levels->forPoints($total);

        return [
            'badge' => $badge->name,
            'description' => $badge->description,
            'earned' => 'Diraih '.tanggal($badge->pivot->earned_at),
            'name' => $user->name,
            'showName' => ! $user->leaderboard_anonymous,
            'classroom' => $user->activeClassroom()?->name,
            'level' => ($level ? 'Level '.$level->name.' · ' : '').angka($total).' poin',
            'program' => 'SMK Go Global · '.setting('program.organizer'),
            'tagline' => 'Kaya Kosakata, Torang Go Global!',
            'hashtags' => '#SMKGoGlobal #Wokabulari',
            'logo' => asset('images/brand/icon-512.png'),
            'fileName' => 'lencana-'.Str::slug($badge->name).'.png',
            'shareText' => 'Aku baru meraih lencana "'.$badge->name.'" di Wokabulari! Kaya Kosakata, Torang Go Global! #SMKGoGlobal #Wokabulari',
        ];
    }

    public function updatedAnonymous(bool $value): void
    {
        auth()->user()->forceFill(['leaderboard_anonymous' => $value])->save();
        Flux::toast(text: $value ? 'Nama disembunyikan di peringkat (tampil inisial).' : 'Nama ditampilkan di peringkat.');
    }

    public function with(PointService $points, LevelService $levels): array
    {
        $user = auth()->user();
        $total = $points->total($user);
        $owned = $user->badges()->get()->keyBy('id');

        return [
            'progress' => $levels->progress($total),
            'levels' => $levels->all(),
            'week' => $points->between($user, now()->startOfWeek(), now()->endOfWeek()),
            'streak' => $user->streak,
            'badges' => Badge::query()->where('is_active', true)->orderBy('id')->get()->map(fn (Badge $b) => ['badge' => $b, 'earned' => $owned->get($b->id)?->pivot?->earned_at]),
            'transactions' => PointTransaction::query()->where('user_id', $user->id)
                ->when($this->kind === 'plus', fn ($q) => $q->where('points', '>', 0))
                ->when($this->kind === 'minus', fn ($q) => $q->where('points', '<', 0))
                ->latest('created_at')->latest('id')->paginate(15),
            'weekPenalty' => (int) PointTransaction::query()->where('user_id', $user->id)->where('points', '<', 0)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('points'),
            'shareBadge' => $shareBadge = $this->shareId ? $owned->get($this->shareId) : null,
            'shareCard' => $this->shareCard($shareBadge, $total, $levels),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Poin & lencana" subtitle="Kumpulkan poin dari hadir, tugas, quiz, dan rating mingguan. Terlambat absen mengurangi poin.">
        <x-slot:actions>
            @if (Route::has('leaderboard'))
                <flux:button icon="trophy" size="sm" :href="route('leaderboard')" wire:navigate>Peringkat</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-gamification.level-card :progress="$progress" :streak="$streak" :week="$week" class="mb-4" />

    <x-card title="Perjalanan level" class="mb-4">
        <ol class="flex items-start justify-between gap-1">
            @foreach ($levels as $level)
                @php($reached = $progress['points'] >= $level->min_points)
                <li class="flex min-w-0 flex-1 flex-col items-center text-center">
                    <span @class(['flex size-10 items-center justify-center rounded-full', 'bg-kunyit text-hutan' => $reached, 'bg-santan text-hutan/40 dark:bg-hutan-800 dark:text-santan/40' => ! $reached])>
                        <flux:icon :name="$level->icon ?? 'sparkles'" class="size-5" />
                    </span>
                    <span class="mt-1 text-xs font-semibold leading-tight">{{ $level->name }}</span>
                    <span class="text-[10px] text-hutan/60 dark:text-santan/60">{{ angka($level->min_points) }}</span>
                </li>
            @endforeach
        </ol>
    </x-card>

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Lencana</h2>
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($badges as ['badge' => $badge, 'earned' => $earned])
            <div @class(['rounded-2xl p-3 text-center ring-1', 'bg-white ring-kunyit dark:bg-hutan-900' => $earned, 'bg-santan/60 ring-woka-100 opacity-70 dark:bg-hutan-900/40 dark:ring-hutan-800' => ! $earned]) wire:key="b-{{ $badge->id }}">
                <span @class(['mx-auto flex size-12 items-center justify-center rounded-full', 'bg-kunyit/20 text-kunyit-700 dark:text-kunyit' => $earned, 'bg-zinc-200 text-zinc-400 dark:bg-hutan-800' => ! $earned])>
                    <flux:icon :name="$earned ? ($badge->icon ?? 'trophy') : 'lock-closed'" class="size-6" />
                </span>
                <p class="mt-2 text-sm font-semibold leading-tight">{{ $badge->name }}</p>
                <p class="mt-1 text-xs text-hutan/60 dark:text-santan/60">{{ $earned ? 'Diraih '.tanggal($earned) : $badge->description }}</p>
                @if ($earned)
                    <flux:button size="xs" variant="ghost" icon="share" class="mt-2" wire:click="share({{ $badge->id }})">Bagikan</flux:button>
                @endif
            </div>
        @endforeach
    </div>

    <x-card class="mb-5">
        <flux:switch wire:model.live="anonymous" label="Sembunyikan nama di peringkat" description="Teman hanya melihat inisial ngana." />
    </x-card>

    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Riwayat poin</h2>
        @if ($weekPenalty < 0)
            <span class="rounded-full bg-rica-50 px-2.5 py-1 text-xs font-semibold text-rica dark:bg-hutan-800" data-test="week-penalty">Pengurangan minggu ini: {{ $weekPenalty }} poin</span>
        @endif
    </div>
    <flux:radio.group wire:model.live="kind" variant="segmented" size="sm" class="mb-3" aria-label="Jenis riwayat">
        <flux:radio value="all" label="Semua" class="data-checked:bg-woka! data-checked:text-white!" />
        <flux:radio value="plus" label="Penambahan" class="data-checked:bg-woka! data-checked:text-white!" />
        <flux:radio value="minus" label="Pengurangan" class="data-checked:bg-rica! data-checked:text-white!" />
    </flux:radio.group>
    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
        @forelse ($transactions as $tx)
            <div class="flex items-center gap-3 px-4 py-3" wire:key="tx-{{ $tx->id }}">
                <span @class(['flex size-9 shrink-0 items-center justify-center rounded-xl', 'bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light' => $tx->points > 0, 'bg-rica-50 text-rica dark:bg-hutan-800' => $tx->points < 0])>
                    <flux:icon :name="$tx->event_key->icon()" class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $tx->description ?? $tx->event_key->label() }}</p>
                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal_jam($tx->created_at) }}</p>
                </div>
                <span @class(['shrink-0 font-display text-lg font-bold', 'text-woka dark:text-woka-light' => $tx->points > 0, 'text-rica' => $tx->points < 0])>{{ $tx->points > 0 ? '+' : '' }}{{ $tx->points }}</span>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">{{ $kind === 'minus' ? 'Tidak ada pengurangan poin. Pertahankan, neh!' : 'Belum ada poin. Absen tepat waktu besok untuk +10!' }}</p>
        @endforelse
    </x-card>
    <div class="mt-3">{{ $transactions->links() }}</div>

    <flux:modal wire:model.self="showShare" class="w-full max-w-md">
        @if ($shareCard)
            <div x-data="badgeShare(@js($shareCard))" wire:key="share-{{ $shareBadge->id }}" class="space-y-4" data-test="badge-share">
                <div>
                    <flux:heading size="lg">Bagikan lencana</flux:heading>
                    <flux:text class="mt-1">Pamerkan "{{ $shareBadge->name }}" ke Instagram, WhatsApp, atau TikTok.</flux:text>
                </div>
                {{-- Ikon lencana dibaca skrip untuk digambar di kartu. --}}
                <template x-ref="icon"><flux:icon :name="$shareBadge->icon ?? 'trophy'" variant="solid" /></template>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg bg-zinc-100 p-1 text-sm dark:bg-hutan-800" role="radiogroup" aria-label="Ukuran gambar">
                        <button type="button" @click="format = 'story'" :class="format === 'story' ? 'bg-woka text-white' : 'text-hutan/70 dark:text-santan/70'" class="min-h-9 rounded-md px-3 font-semibold">Story 9:16</button>
                        <button type="button" @click="format = 'square'" :class="format === 'square' ? 'bg-woka text-white' : 'text-hutan/70 dark:text-santan/70'" class="min-h-9 rounded-md px-3 font-semibold">Kotak 1:1</button>
                    </div>
                    <label class="flex min-h-9 items-center gap-2 text-sm">
                        <input type="checkbox" x-model="showName" class="size-4 rounded border-zinc-300 text-woka focus:ring-woka"> Tampilkan nama
                    </label>
                </div>

                <div class="relative flex justify-center rounded-xl bg-zinc-50 p-2 dark:bg-hutan-950">
                    <canvas x-ref="canvas" class="max-h-[50vh] w-auto max-w-full rounded-lg shadow" aria-label="Pratinjau kartu lencana"></canvas>
                    <span :class="busy ? 'flex' : 'hidden'" class="absolute inset-0 hidden items-center justify-center rounded-xl bg-white/70 text-xs text-hutan/70 dark:bg-hutan-950/70 dark:text-santan/70">Menyiapkan gambar…</span>
                </div>
                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button variant="ghost" icon="arrow-down-tray" x-on:click="download()">Unduh gambar</flux:button>
                    <flux:button variant="primary" icon="share" x-show="canShare" x-on:click="share()">Bagikan</flux:button>
                </div>
                <p class="text-xs text-hutan/50 dark:text-santan/50">Gambar dibuat di HP ngana dan tidak diunggah ke server. Tanpa tombol Bagikan? Unduh lalu unggah dari galeri.</p>
            </div>
        @endif
    </flux:modal>
</div>
