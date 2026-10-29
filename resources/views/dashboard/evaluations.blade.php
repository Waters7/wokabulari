{{-- Pengingat survei yang belum diisi (peserta & pengajar) --}}
@php
    $user = auth()->user();
    $pendingSurveys = ($user->isParticipant() || $user->isTeacher())
        ? \App\Models\ProgramEvaluation::query()->for($user)->open()->orderBy('closes_at')->get()
            ->reject(fn ($evaluation) => $evaluation->hasResponded($user))->values()
        : collect();
@endphp

@if ($pendingSurveys->isNotEmpty())
    @php($next = $pendingSurveys->first())
    <a href="{{ route($pendingSurveys->count() === 1 ? 'evaluations.fill' : 'evaluations.index', $pendingSurveys->count() === 1 ? $next : []) }}" wire:navigate
       class="flex items-center gap-3 rounded-2xl border-s-4 border-kunyit bg-white px-4 py-3 ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800" data-test="survey-reminder">
        <flux:icon.clipboard-document-list class="size-5 shrink-0 text-woka dark:text-woka-light" />
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold">
                {{ $pendingSurveys->count() === 1 ? 'Isi survei: '.$next->title : $pendingSurveys->count().' survei menunggu diisi' }}
            </span>
            <span class="block text-xs text-hutan/50 dark:text-santan/50">Tenggat {{ tanggal_jam($next->closes_at) }}</span>
        </span>
        <flux:icon.chevron-right class="size-4 shrink-0 text-hutan/40" />
    </a>
@endif
