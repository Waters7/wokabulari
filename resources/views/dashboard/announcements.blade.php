{{-- Beranda: 3 pengumuman terbaru untuk pengguna (penting di atas), ditandai sumbernya. --}}
@php
    $latestAnnouncements = \App\Models\Announcement::query()->visibleTo(auth()->user())->with('creator')
        ->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get();
    $readAnnouncementIds = \App\Models\AnnouncementRead::query()->where('user_id', auth()->id())
        ->whereIn('announcement_id', $latestAnnouncements->pluck('id'))->pluck('announcement_id')->flip();
@endphp

@if ($latestAnnouncements->isNotEmpty())
    <x-card title="Pengumuman terbaru">
        <x-slot:action>
            <flux:button size="sm" variant="ghost" :href="route('announcements.index')" wire:navigate icon:trailing="arrow-right">Semua</flux:button>
        </x-slot:action>
        <div class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($latestAnnouncements as $item)
                <a href="{{ route('announcements.index', ['buka' => $item->id]) }}" wire:navigate class="flex items-start gap-3 py-2.5 first:pt-0" wire:key="da-{{ $item->id }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $item->source->accentClasses() }}">
                        <flux:icon :name="$item->source->icon()" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-1 text-xs font-semibold text-hutan/60 dark:text-santan/60">
                            {{ $item->source->label() }} · {{ tanggal($item->published_at, 'j M') }}
                            @if ($item->is_pinned)<flux:badge size="sm" color="red">Penting</flux:badge>@endif
                        </span>
                        <span @class(['block truncate', 'font-bold' => ! $readAnnouncementIds->has($item->id), 'font-semibold' => $readAnnouncementIds->has($item->id)])>{{ $item->title }}</span>
                    </span>
                    @unless ($readAnnouncementIds->has($item->id))
                        <span class="mt-2 size-2 shrink-0 rounded-full bg-rica" aria-label="Belum dibaca"></span>
                    @endunless
                </a>
            @endforeach
        </div>
    </x-card>
@endif
