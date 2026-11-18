{{-- Galeri dokumentasi foto per sesi di Pusat Laporan (laporan Absen per sesi). --}}
@php
    $user = auth()->user();
    $all = collect($gallery)->filter(fn (array $item) => $user->can('viewAny', [\App\Models\SessionDocumentation::class, $item['session']]))->values();
    $incomplete = $all->where('incomplete', true)->count();
    $items = $docFilter === 'missing' ? $all->where('incomplete', true)->values() : $all;
@endphp

@if ($all->isNotEmpty())
    <details open class="group mb-4" wire:key="rs-gallery" data-test="session-gallery">
        <summary class="mb-2 flex cursor-pointer list-none items-center gap-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">
            <flux:icon.chevron-right class="size-4 transition group-open:rotate-90" />
            Dokumentasi foto per sesi <span class="font-normal normal-case">({{ $all->count() }} sesi)</span>
        </summary>

        <div class="mb-3 flex flex-wrap gap-1.5">
            @foreach (['' => 'Semua sesi ('.$all->count().')', 'missing' => 'Belum lengkap ('.$incomplete.')'] as $value => $text)
                <button type="button" wire:click="$set('docFilter', '{{ $value }}')" wire:key="df-{{ $value ?: 'all' }}"
                        @class(['min-h-9 rounded-full px-3 text-sm font-semibold ring-1 transition',
                            'bg-woka text-white ring-woka' => $docFilter === $value,
                            'bg-white text-hutan/70 ring-zinc-200 hover:ring-woka-200 dark:bg-hutan-900 dark:text-santan/70 dark:ring-hutan-800' => $docFilter !== $value])>
                    {{ $text }}
                </button>
            @endforeach
        </div>

        @if ($items->isEmpty())
            <p class="rounded-2xl bg-white px-4 py-6 text-center text-sm text-hutan/60 ring-1 ring-zinc-200/70 dark:bg-hutan-900 dark:text-santan/60 dark:ring-hutan-800">Semua sesi pada periode ini sudah lengkap dokumentasinya.</p>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($items->take($galleryLimit) as $item)
                    @php
                        $session = $item['session'];
                    @endphp
                    <li wire:key="g-{{ $session->id }}" @class(['min-w-0 rounded-2xl bg-white p-3 ring-1 dark:bg-hutan-900',
                        'ring-rica/40' => $item['incomplete'], 'ring-zinc-200/70 dark:ring-hutan-800' => ! $item['incomplete']])>
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold">{{ $session->classroom->name }} · {{ $session->topic }}</p>
                                <p class="truncate text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($session->date, 'D, j M') }} · {{ $session->timeRange() }} · {{ $session->teacher?->name ?? '–' }}</p>
                            </div>
                            <a href="{{ route('teach.sessions.show', $session) }}" wire:navigate class="shrink-0 text-xs font-semibold text-woka hover:underline dark:text-woka-light">Buka sesi</a>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            @foreach (\App\Enums\SessionPhase::cases() as $phase)
                                @php
                                    $part = $item[$phase->value];
                                @endphp
                                <div class="min-w-0" wire:key="g-{{ $session->id }}-{{ $phase->value }}">
                                    <p class="text-xs font-semibold text-hutan/60 dark:text-santan/60">{{ $phase->label() }}</p>
                                    @if ($part['files']->isNotEmpty())
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            @foreach ($part['files'] as $doc)
                                                @php
                                                    $url = $doc->url();
                                                @endphp
                                                <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $doc->source->label() }} · {{ jam($doc->created_at) }}"
                                                   class="block size-16 overflow-hidden rounded-lg bg-zinc-50 ring-1 ring-zinc-200 dark:bg-hutan-800 dark:ring-hutan-800">
                                                    @if ($doc->isImage())
                                                        <img src="{{ $url }}" alt="Foto {{ strtolower($phase->label()) }} {{ $session->classroom->name }}" class="size-full object-cover" loading="lazy">
                                                    @else
                                                        <span class="flex size-full items-center justify-center text-hutan/60 dark:text-santan/60"><flux:icon.document-text class="size-6" /></span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @elseif ($part['missing'])
                                        <p class="mt-1 text-sm font-semibold text-rica">Belum ada</p>
                                    @else
                                        <p class="mt-1 text-sm text-hutan/50 dark:text-santan/50">{{ $item['required'] ? 'Belum waktunya' : 'Tidak wajib' }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($items->count() > $galleryLimit)
                <div class="mt-3 text-center">
                    <flux:button size="sm" wire:click="$set('galleryLimit', {{ $galleryLimit + 24 }})">Tampilkan lebih banyak ({{ $items->count() - $galleryLimit }} lagi)</flux:button>
                </div>
            @endif
        @endif
    </details>
@endif
