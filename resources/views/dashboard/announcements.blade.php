{{-- Beranda: 3 pengumuman terbaru untuk pengguna (penting di atas), ditandai sumbernya. --}}
@php
    $latestAnnouncements = \App\Models\Announcement::query()->visibleTo(auth()->user())->with('creator')
        ->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get();
    $readAnnouncementIds = \App\Models\AnnouncementRead::query()->where('user_id', auth()->id())
        ->whereIn('announcement_id', $latestAnnouncements->pluck('id'))->pluck('announcement_id')->flip();
@endphp

@if ($latestAnnouncements->isNotEmpty())
    <section>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Pengumuman</h2>
            <a href="{{ route('announcements.index') }}" wire:navigate class="text-sm font-medium text-woka hover:underline dark:text-woka-light">Semua</a>
        </div>
        <div class="divide-y divide-zinc-100 rounded-2xl bg-white ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:bg-hutan-900 dark:ring-hutan-800">
            @foreach ($latestAnnouncements as $item)
                <a href="{{ route('announcements.index', ['buka' => $item->id]) }}" wire:navigate class="flex items-start gap-3 px-4 py-3" wire:key="da-{{ $item->id }}">
                    <flux:icon :name="$item->source->icon()" class="mt-0.5 size-5 shrink-0 text-hutan/40 dark:text-santan/40" />
                    <span class="min-w-0 flex-1">
                        <span @class(['block truncate', 'font-semibold' => ! $readAnnouncementIds->has($item->id), 'text-hutan/80 dark:text-santan/80' => $readAnnouncementIds->has($item->id)])>{{ $item->title }}</span>
                        <span class="flex items-center gap-1.5 text-xs text-hutan/50 dark:text-santan/50">
                            {{ $item->source->label() }} · {{ tanggal($item->published_at, 'j M') }}
                            @if ($item->is_pinned)<span class="font-semibold text-red-600 dark:text-red-400">Penting</span>@endif
                        </span>
                    </span>
                    @unless ($readAnnouncementIds->has($item->id))
                        <span class="mt-2 size-2 shrink-0 rounded-full bg-woka" aria-label="Belum dibaca"></span>
                    @endunless
                </a>
            @endforeach
        </div>
    </section>
@endif
