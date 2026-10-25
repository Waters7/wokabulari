<?php

use App\Actions\Assignments\GradeSubmission;
use App\Enums\SubmissionStatus;
use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Detail tugas')] class extends Component {
    use WithFileUploads;

    public Assignment $assignment;

    #[Url]
    public string $filter = 'ungraded';

    /** Indeks peserta yang sedang dinilai pada mode cepat. */
    public int $index = 0;

    public string $score = '';

    public string $feedback = '';

    public $voiceNote = null;

    public function mount(Assignment $assignment): void
    {
        $this->authorize('manage', $assignment);
        $this->assignment = $assignment->load(['classroom', 'session', 'attachments']);
        $this->loadCurrent();
    }

    /** @return \Illuminate\Support\Collection<int, AssignmentSubmission> */
    protected function queue()
    {
        return $this->assignment->submissions()
            ->with(['user', 'files'])
            ->when($this->filter === 'ungraded', fn ($q) => $q->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Late]))
            ->orderBy('submitted_at')
            ->get()
            ->values();
    }

    protected function current(): ?AssignmentSubmission
    {
        $queue = $this->queue();
        $this->index = max(0, min($this->index, $queue->count() - 1));

        return $queue->get($this->index);
    }

    protected function loadCurrent(): void
    {
        $current = $this->current();
        $this->score = $current?->score !== null ? (string) $current->score : '';
        $this->feedback = $current?->feedback ?? '';
        $this->voiceNote = null;
        $this->resetErrorBag();
    }

    public function updatedFilter(): void
    {
        $this->index = 0;
        $this->loadCurrent();
    }

    public function go(int $delta): void
    {
        $this->index += $delta;
        $this->loadCurrent();
    }

    public function open(int $submissionId): void
    {
        $this->filter = 'all';
        $this->index = (int) $this->queue()->search(fn ($s) => $s->id === $submissionId);
        $this->loadCurrent();
    }

    public function grade(GradeSubmission $grader): void
    {
        $this->authorize('manage', $this->assignment);
        $submission = $this->current();
        abort_unless($submission, 404);

        $this->resetErrorBag();
        $this->validate(collect(AssignmentRequest::gradeRules($this->assignment->max_score))->all(), [], ['score' => 'nilai', 'voiceNote' => 'voice note']);

        $graded = $grader->grade($submission, auth()->user(), (float) str_replace(',', '.', $this->score), $this->feedback ?: null, $this->voiceNote);

        Flux::toast(variant: 'success', text: "{$submission->user->name}: nilai akhir ".angka($graded->final_score, 1).($graded->is_late ? ' (dipotong karena terlambat)' : ''));

        // Pada mode "belum dinilai", item ini keluar dari antrean; indeks tetap menunjuk berikutnya.
        if ($this->filter === 'all') {
            $this->index++;
        }
        $this->loadCurrent();
    }

    public function requestRevision(GradeSubmission $grader): void
    {
        $this->authorize('manage', $this->assignment);
        $submission = $this->current();
        abort_unless($submission, 404);

        try {
            $grader->requestRevision($submission, auth()->user(), $this->feedback, $this->voiceNote);
        } catch (\InvalidArgumentException $e) {
            $this->addError('feedback', $e->getMessage());

            return;
        }

        Flux::toast(text: "Revisi diminta untuk {$submission->user->name}.");
        if ($this->filter === 'all') {
            $this->index++;
        }
        $this->loadCurrent();
    }

    public function with(): array
    {
        $submissions = $this->assignment->submissions()->get()->keyBy('user_id');
        $participants = $this->assignment->classroom->participants()->orderBy('name')->get();
        $queue = $this->queue();

        return [
            'current' => $this->current(),
            'queueCount' => $queue->count(),
            'participants' => $participants,
            'submissions' => $submissions,
            'counts' => [
                'submitted' => $submissions->count(),
                'ungraded' => $submissions->filter(fn ($s) => $s->status->awaitingGrade())->count(),
                'graded' => $submissions->where('status', SubmissionStatus::Graded)->count(),
                'missing' => $participants->count() - $submissions->count(),
            ],
        ];
    }
}; ?>

<div>
    <x-page-header :title="$assignment->title" :back="route('teach.assignments.index')"
                   :subtitle="$assignment->classroom->label().' · tenggat '.tanggal_jam($assignment->due_at).' · maks '.angka($assignment->max_score)" />

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat label="Mengumpulkan" :value="$counts['submitted']" icon="inbox-arrow-down" />
        <x-stat label="Perlu dinilai" :value="$counts['ungraded']" icon="pencil-square" tone="kunyit" />
        <x-stat label="Dinilai" :value="$counts['graded']" icon="check-badge" />
        <x-stat label="Belum kumpul" :value="$counts['missing']" icon="clock" tone="rica" />
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        {{-- Mode penilaian cepat --}}
        <x-card>
            <div class="mb-3 flex items-center justify-between gap-2">
                <flux:radio.group wire:model.live="filter" variant="segmented" size="sm">
                    <flux:radio value="ungraded">Perlu dinilai</flux:radio>
                    <flux:radio value="all">Semua</flux:radio>
                </flux:radio.group>
                <span class="text-sm text-hutan/60">{{ $queueCount ? min($this->index + 1, $queueCount).' / '.$queueCount : '0' }}</span>
            </div>

            @if ($current)
                <div x-data="{ startX: null }"
                     x-on:touchstart.passive="startX = $event.touches[0].clientX"
                     x-on:touchend="if (startX !== null) { const dx = $event.changedTouches[0].clientX - startX; if (Math.abs(dx) > 70) $wire.go(dx < 0 ? 1 : -1); startX = null }"
                     wire:key="cur-{{ $current->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="go(-1)" :disabled="$this->index === 0" aria-label="Sebelumnya" />
                        <div class="min-w-0 text-center">
                            <p class="truncate font-display text-lg font-bold">{{ $current->user->name }}</p>
                            <p class="text-xs text-hutan/60">Dikumpulkan {{ tanggal_jam($current->submitted_at) }}
                                @if ($current->is_late) · <span class="font-semibold text-rica">terlambat (−{{ $assignment->late_penalty_percent }}%)</span>@endif
                                @if ($current->revision_count) · revisi ke-{{ $current->revision_count }}@endif
                            </p>
                            <x-status-badge :status="$current->status" />
                        </div>
                        <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="go(1)" :disabled="$this->index >= $queueCount - 1" aria-label="Berikutnya" />
                    </div>

                    <div class="mt-4 space-y-3">
                        @if ($current->text_answer)
                            <div class="whitespace-pre-line rounded-xl bg-santan p-3 text-sm dark:bg-hutan-950">{{ $current->text_answer }}</div>
                        @endif
                        @foreach ($current->files as $file)
                            @if ($file->isImage())
                                <a href="{{ $file->url() }}" target="_blank"><img src="{{ $file->url() }}" alt="Foto tugas" class="max-h-96 w-full rounded-xl object-contain ring-1 ring-woka-100"></a>
                            @elseif ($file->isAudio())
                                <audio controls preload="metadata" class="w-full" src="{{ $file->url() }}"></audio>
                            @else
                                <flux:button size="sm" icon="paper-clip" :href="$file->url()" target="_blank">{{ $file->original_name ?? 'Berkas' }}</flux:button>
                            @endif
                        @endforeach
                    </div>

                    <form wire:submit="grade" class="mt-4 space-y-3 border-t border-woka-100 pt-4 dark:border-hutan-800">
                        <div class="flex items-end gap-3">
                            <flux:input wire:model="score" inputmode="decimal" :label="'Nilai (0–'.angka($assignment->max_score).')'" class="w-32" />
                            @if ($current->final_score !== null)
                                <p class="pb-2 text-sm">Nilai akhir: <span class="font-bold">{{ angka($current->final_score, 1) }}</span></p>
                            @endif
                        </div>
                        <flux:textarea wire:model="feedback" label="Komentar" rows="2" />
                        <x-audio-recorder model="voiceNote" label="Voice note (opsional)" :max-seconds="120" wire:key="vn-{{ $current->id }}" />
                        @if ($current->feedback_audio_path)
                            <audio controls class="w-full" src="{{ $current->feedbackAudioUrl() }}"></audio>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <flux:button type="submit" variant="primary" icon="check">Simpan nilai</flux:button>
                            <flux:button icon="arrow-path" wire:click="requestRevision">Minta revisi</flux:button>
                        </div>
                    </form>
                </div>
            @else
                <x-empty-state icon="check-badge" title="Semua pengumpulan sudah dinilai" description="Mantap! Tidak ada antrean penilaian." />
            @endif
        </x-card>

        <div class="space-y-4">
            <x-card title="Instruksi">
                <p class="whitespace-pre-line text-sm">{{ $assignment->instructions }}</p>
                @if ($assignment->session)
                    <p class="mt-2 text-xs text-hutan/60">Ditautkan ke sesi {{ tanggal($assignment->session->date) }} · {{ $assignment->session->topic }}</p>
                @endif
                @foreach ($assignment->attachments as $attachment)
                    <flux:button class="mt-2" size="sm" icon="paper-clip" :href="$attachment->url()" target="_blank">{{ $attachment->original_name }}</flux:button>
                @endforeach
            </x-card>

            <x-card title="Status peserta" :padding="false">
                <ul class="divide-y divide-woka-100 dark:divide-hutan-800">
                    @foreach ($participants as $participant)
                        @php($s = $submissions->get($participant->id))
                        <li class="flex items-center justify-between gap-2 px-4 py-2">
                            <span class="truncate text-sm font-semibold">{{ $participant->name }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                @if ($s)
                                    @if ($s->final_score !== null)<span class="text-sm font-bold">{{ angka($s->final_score, 1) }}</span>@endif
                                    <button type="button" wire:click="open({{ $s->id }})"><x-status-badge :status="$s->status" /></button>
                                @else
                                    <x-status-badge :status="SubmissionStatus::NotSubmitted" />
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    </div>
</div>
