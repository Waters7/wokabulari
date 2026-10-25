<?php

use App\Actions\Announcements\ManageAnnouncements;
use App\Enums\AnnouncementSource;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengumuman')] class extends Component {
    use WithPagination;

    /** Filter sumber: semua | team | teacher | dormitory */
    #[Url(as: 'dari')]
    public string $source = 'all';

    /** Pengumuman yang dibuka dari notifikasi. */
    #[Url(as: 'buka')]
    public ?int $openId = null;

    public function mount(ManageAnnouncements $announcements): void
    {
        if ($this->openId && ($announcement = Announcement::query()->visibleTo(auth()->user())->find($this->openId))) {
            $announcements->markRead($announcement, auth()->user());
        }
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function read(int $id, ManageAnnouncements $announcements): void
    {
        $announcement = Announcement::query()->visibleTo(auth()->user())->findOrFail($id);
        $announcements->markRead($announcement, auth()->user());
    }

    public function readAll(ManageAnnouncements $announcements): void
    {
        Announcement::query()->visibleTo(auth()->user())->whereDoesntHave('reads', fn ($q) => $q->where('user_id', auth()->id()))
            ->get()->each(function (Announcement $a) use ($announcements) {
                $announcements->markRead($a, auth()->user());
            });
    }

    public function with(): array
    {
        $user = auth()->user();
        $base = fn () => Announcement::query()->visibleTo($user);
        $readIds = AnnouncementRead::query()->where('user_id', $user->id)->pluck('announcement_id');

        return [
            'items' => $base()->with(['creator', 'classroom', 'dormitory'])
                ->when($this->source !== 'all', fn ($q) => $q->where('source', $this->source))
                ->orderByDesc('is_pinned')->latest('published_at')->paginate(15),
            'readIds' => $readIds->flip(),
            'unread' => $base()->whereNotIn('id', $readIds)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source'),
            'sources' => AnnouncementSource::cases(),
            'canManage' => $user->can('create', Announcement::class),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Pengumuman" subtitle="Kabar dari tim kerja, pengajar, dan pengelola asrama.">
        <x-slot:actions>
            @if ($unread->sum() > 0)
                <flux:button size="sm" icon="check" wire:click="readAll">Tandai semua dibaca</flux:button>
            @endif
            @if ($canManage)
                <flux:button size="sm" variant="primary" icon="pencil-square" :href="route('manage.announcements')" wire:navigate>Kelola</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Filter per sumber dengan jumlah belum dibaca --}}
    <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1" role="tablist">
        @foreach (array_merge([['all', 'Semua', 'inbox', null]], array_map(fn ($s) => [$s->value, $s->label(), $s->icon(), $s], $sources)) as [$key, $label, $icon, $src])
            @php($count = $key === 'all' ? $unread->sum() : ($unread[$key] ?? 0))
            <button type="button" wire:click="$set('source', '{{ $key }}')" role="tab" aria-selected="{{ $source === $key ? 'true' : 'false' }}" wire:key="f-{{ $key }}"
                    @class(['flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-semibold ring-1 transition',
                        'bg-woka text-white ring-woka dark:bg-woka-light dark:text-hutan' => $source === $key,
                        'bg-white ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800' => $source !== $key])>
                <flux:icon :name="$icon" class="size-4" />
                {{ $label }}
                @if ($count > 0)
                    <span class="rounded-full bg-rica px-1.5 text-xs text-white">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($items as $item)
            @php($isRead = $readIds->has($item->id))
            <article x-data="{ open: @js($openId === $item->id) }" wire:key="a-{{ $item->id }}"
                     class="overflow-hidden rounded-2xl border-s-4 bg-white ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800 {{ $item->source === \App\Enums\AnnouncementSource::Team ? 'border-s-woka dark:border-s-woka-light' : ($item->source === \App\Enums\AnnouncementSource::Teacher ? 'border-s-kunyit' : 'border-s-violet-500') }}">
                <button type="button" class="flex w-full items-start gap-3 p-4 text-start"
                        x-on:click="open = ! open; if (open) $wire.read({{ $item->id }})" :aria-expanded="open">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $item->source->accentClasses() }}">
                        <flux:icon :name="$item->source->icon()" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-1">
                            <flux:badge size="sm" :color="$item->source->color()">{{ $item->source->label() }}</flux:badge>
                            @if ($item->is_pinned)
                                <flux:badge size="sm" color="red" icon="bookmark">Penting</flux:badge>
                            @endif
                            <span class="text-xs text-hutan/60 dark:text-santan/60">untuk {{ $item->audienceLabel() }}</span>
                        </span>
                        <span @class(['mt-1 block leading-snug', 'font-bold' => ! $isRead, 'font-semibold' => $isRead])>{{ $item->title }}</span>
                        <span class="mt-0.5 block text-xs text-hutan/60 dark:text-santan/60">{{ $item->creator?->name }} · {{ tanggal_jam($item->published_at) }}</span>
                        <span x-show="!open" class="mt-1 line-clamp-2 text-sm text-hutan/70 dark:text-santan/70">{{ $item->body }}</span>
                    </span>
                    @unless ($isRead)
                        <span class="mt-1 size-2.5 shrink-0 rounded-full bg-rica" aria-label="Belum dibaca"></span>
                    @endunless
                </button>
                <div x-show="open" x-collapse class="px-4 pb-4 ps-[4.25rem]">
                    <p class="whitespace-pre-line text-sm leading-relaxed">{{ $item->body }}</p>
                </div>
            </article>
        @empty
            <x-empty-state icon="megaphone" title="Belum ada pengumuman" description="Pengumuman dari tim kerja, pengajar, dan asrama akan tampil di sini." />
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
