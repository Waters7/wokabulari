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
       class="mt-4 flex items-center gap-3 rounded-2xl bg-kunyit/30 p-4 ring-1 ring-kunyit transition hover:bg-kunyit/40" data-test="survey-reminder">
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-woka dark:bg-hutan-900">
            <flux:icon.clipboard-document-list class="size-6" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block font-semibold leading-tight">
                {{ $pendingSurveys->count() === 1 ? 'Isi survei: '.$next->title : $pendingSurveys->count().' survei menunggu diisi' }}
            </span>
            <span class="block text-xs text-hutan/70 dark:text-santan/70">Tenggat terdekat {{ tanggal_jam($next->closes_at) }}</span>
        </span>
        <flux:icon.chevron-right class="size-5 shrink-0" />
    </a>
@endif
