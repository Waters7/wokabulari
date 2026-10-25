{{-- Pretest–posttest & riwayat quiz (bagian halaman Nilai & masukan) --}}
@if ($section['ngain'])
    @php($row = $section['ngain'])
    <x-card class="mt-4" title="Pretest vs posttest">
        <div class="grid grid-cols-3 gap-3 text-center">
            <div>
                <p class="text-xs font-semibold uppercase text-hutan/60 dark:text-santan/60">Pretest</p>
                <p class="font-display text-3xl font-extrabold text-kunyit-700">{{ $row['pre'] !== null ? angka($row['pre'], 0) : '–' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-hutan/60 dark:text-santan/60">Posttest</p>
                <p class="font-display text-3xl font-extrabold text-woka">{{ $row['post'] !== null ? angka($row['post'], 0) : '–' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-hutan/60 dark:text-santan/60">N-Gain</p>
                <p class="font-display text-3xl font-extrabold">{{ $row['gain'] !== null ? angka($row['gain'], 2) : '–' }}</p>
                @if ($row['category'])<flux:badge size="sm" :color="['Tinggi' => 'green', 'Sedang' => 'amber', 'Rendah' => 'red'][$row['category']]">{{ $row['category'] }}</flux:badge>@endif
            </div>
        </div>
    </x-card>
@endif

@if ($section['attempts']->isNotEmpty())
    <x-card class="mt-4" title="Riwayat quiz" :padding="false">
        <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
            @foreach ($section['attempts'] as $attempt)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <div class="min-w-0">
                        @if (Route::has('quizzes.result') && auth()->user()->isParticipant())
                            <a href="{{ route('quizzes.result', $attempt) }}" wire:navigate class="block truncate text-sm font-semibold hover:text-woka">{{ $attempt->quiz->title }}</a>
                        @else
                            <p class="truncate text-sm font-semibold">{{ $attempt->quiz->title }}</p>
                        @endif
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal($attempt->submitted_at) }} · <x-status-badge :status="$attempt->quiz->type" /></p>
                    </div>
                    <span @class(['font-display text-xl font-bold', 'text-woka' => $attempt->passed(), 'text-rica' => ! $attempt->passed()])>{{ angka($attempt->percentage, 0) }}</span>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
