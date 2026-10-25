{{-- Riwayat nilai tugas (bagian halaman Nilai & masukan) --}}
<x-card class="mt-4" title="Riwayat tugas" :padding="false">
    <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
        @foreach ($section['submissions'] as $submission)
            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                <div class="min-w-0">
                    @if (Route::has('assignments.show') && auth()->user()->isParticipant())
                        <a href="{{ route('assignments.show', $submission->assignment_id) }}" wire:navigate class="block truncate text-sm font-semibold hover:text-woka">{{ $submission->assignment->title }}</a>
                    @else
                        <p class="truncate text-sm font-semibold">{{ $submission->assignment->title }}</p>
                    @endif
                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($submission->submitted_at) }} · <x-status-badge :status="$submission->status" /></p>
                </div>
                <span class="font-display text-xl font-bold">{{ $submission->final_score !== null ? angka($submission->percentage(), 0) : '–' }}</span>
            </li>
        @endforeach
    </ul>
</x-card>
