<?php

use App\Actions\Assignments\SubmitAssignment;
use App\Enums\SubmissionStatus;
use App\Http\Requests\AssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Tugas')] class extends Component {
    use WithFileUploads;

    public Assignment $assignment;

    public string $text = '';

    public array $files = [];

    public array $photos = [];

    public $audio = null;

    public ?string $error = null;

    public function mount(Assignment $assignment): void
    {
        $this->authorize('view', $assignment);
        $this->assignment = $assignment->load(['attachments', 'classroom', 'teacher']);
        $this->text = $this->submission()?->text_answer ?? '';
    }

    protected function submission(): ?AssignmentSubmission
    {
        return $this->assignment->submissions()->where('user_id', auth()->id())->with('files')->first();
    }

    public function submit(SubmitAssignment $submit): void
    {
        $this->authorize('submit', $this->assignment);
        $this->error = null;
        $this->validate(AssignmentRequest::submissionRules(), [], ['files.*' => 'berkas', 'photos.*' => 'foto', 'audio' => 'rekaman']);

        try {
            $submission = $submit->handle($this->assignment, auth()->user(), $this->text, $this->files, $this->photos, $this->audio);
        } catch (\InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('files', 'photos', 'audio');
        Flux::toast(variant: 'success', text: $submission->is_late ? 'Tugas terkumpul (terlambat). Lain kali lebih cepat, neh!' : 'Mantap! Tugas so terkumpul tepat waktu.');
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function with(): array
    {
        $submission = $this->submission();
        $status = $submission?->status ?? SubmissionStatus::NotSubmitted;
        $type = $this->assignment->submission_type;

        $canSubmit = $this->assignment->isOpen() && (
            ! $submission
            || $status === SubmissionStatus::RevisionRequested
            || ($this->assignment->allow_resubmit && $status->awaitingGrade())
        );

        return compact('submission', 'status', 'type', 'canSubmit');
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header :title="$assignment->title" :back="route('assignments.index')"
                   :subtitle="$assignment->classroom->label().' · '.$assignment->teacher?->name" />

    <x-card class="mb-4">
        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
            <x-status-badge :status="$status" />
            <span @class(['font-semibold', 'text-rica' => $assignment->isOverdue() || $assignment->isDueSoon()])>Tenggat {{ tanggal_jam($assignment->due_at) }} WITA</span>
            <span class="text-hutan/60 dark:text-santan/60">· maks {{ angka($assignment->max_score) }}</span>
        </div>
        <p class="whitespace-pre-line">{{ $assignment->instructions }}</p>
        @if ($assignment->attachments->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($assignment->attachments as $attachment)
                    <flux:button size="sm" icon="paper-clip" :href="$attachment->url()" target="_blank">{{ $attachment->original_name }}</flux:button>
                @endforeach
            </div>
        @endif
        <p class="mt-3 text-xs text-hutan/60 dark:text-santan/60">
            Jenis pengumpulan: {{ $type->label() }}.
            @if ($assignment->allow_late) Terlambat tetap diterima dengan potongan {{ $assignment->late_penalty_percent }}%. @else Tidak menerima keterlambatan. @endif
            @if ($assignment->allow_resubmit) Boleh kumpul ulang sebelum dinilai. @endif
        </p>
    </x-card>

    @if ($submission && in_array($status, [SubmissionStatus::Graded, SubmissionStatus::RevisionRequested], true))
        <x-card class="mb-4" :title="$status === SubmissionStatus::Graded ? 'Nilai & komentar' : 'Perlu revisi'">
            @if ($status === SubmissionStatus::Graded)
                <p class="font-display text-4xl font-extrabold text-woka">{{ angka($submission->final_score, 1) }}<span class="text-lg text-hutan/50 dark:text-santan/50">/{{ angka($assignment->max_score) }}</span></p>
                @if ($submission->is_late && $submission->score != $submission->final_score)
                    <p class="text-xs text-rica">Nilai awal {{ angka($submission->score, 1) }}, dipotong {{ $assignment->late_penalty_percent }}% karena terlambat.</p>
                @endif
            @endif
            @if ($submission->feedback)
                <p class="mt-2 whitespace-pre-line text-sm">{{ $submission->feedback }}</p>
            @endif
            @if ($submission->feedback_audio_path)
                <p class="mt-2 text-xs font-semibold">Voice note pengajar</p>
                <audio controls class="w-full" src="{{ $submission->feedbackAudioUrl() }}"></audio>
            @endif
        </x-card>
    @endif

    @if ($submission)
        <x-card class="mb-4" title="Pengumpulan ngana">
            <p class="text-xs text-hutan/60 dark:text-santan/60">Dikumpulkan {{ tanggal_jam($submission->submitted_at) }} @if ($submission->is_late)<span class="font-semibold text-rica">· terlambat</span>@endif</p>
            @if ($submission->text_answer)
                <p class="mt-2 whitespace-pre-line rounded-xl bg-santan p-3 text-sm dark:bg-hutan-950">{{ $submission->text_answer }}</p>
            @endif
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach ($submission->files as $file)
                    @if ($file->isImage())
                        <img src="{{ $file->url() }}" alt="Foto tugas" class="aspect-square w-full rounded-lg object-cover">
                    @elseif ($file->isAudio())
                        <audio controls class="col-span-3 w-full" src="{{ $file->url() }}"></audio>
                    @else
                        <a href="{{ $file->url() }}" target="_blank" class="col-span-3 text-sm font-semibold text-woka underline">{{ $file->original_name }}</a>
                    @endif
                @endforeach
            </div>
        </x-card>
    @endif

    @if ($canSubmit)
        <x-card :title="$submission ? 'Kumpulkan ulang' : 'Kumpulkan tugas'">
            <form wire:submit="submit" class="space-y-4">
                @if ($type->allowsText())
                    <flux:textarea wire:model="text" label="Jawaban" rows="5" placeholder="Tulis jawaban ngana di sini…" />
                @endif

                @if ($type->allowsPhotos())
                    <div>
                        <flux:input type="file" wire:model="photos" multiple accept="image/*" capture="environment" label="Foto tugas tulis" description="Ambil foto langsung dari kamera. Bisa lebih dari satu." />
                        <div wire:loading wire:target="photos" class="mt-1 text-xs text-woka">Mengunggah foto…</div>
                        @if ($photos)
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                @foreach ($photos as $i => $photo)
                                    <div class="relative" wire:key="ph-{{ $i }}">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                                        <button type="button" wire:click="removePhoto({{ $i }})" class="absolute end-1 top-1 rounded-full bg-hutan/70 p-1 text-white" aria-label="Hapus foto"><flux:icon.x-mark class="size-4" /></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <flux:error name="photos.0" />
                    </div>
                @endif

                @if ($type->allowsFiles())
                    <div>
                        <flux:input type="file" wire:model="files" multiple label="Berkas" description="PDF, dokumen, gambar, atau audio." />
                        <div wire:loading wire:target="files" class="mt-1 text-xs text-woka">Mengunggah…</div>
                        <flux:error name="files.0" />
                    </div>
                @endif

                @if ($type->allowsAudio())
                    <x-audio-recorder model="audio" label="Rekam jawaban (speaking)" />
                    <flux:error name="audio" />
                @endif

                @if ($error)
                    <flux:callout icon="exclamation-triangle" color="red" :text="$error" />
                @endif

                @if ($assignment->isOverdue())
                    <flux:callout icon="clock" color="amber" text="Tenggat sudah lewat. Pengumpulan tetap diterima dengan potongan nilai {{ $assignment->late_penalty_percent }}%." />
                @endif

                <flux:button type="submit" variant="primary" icon="paper-airplane" class="w-full" wire:loading.attr="disabled" wire:target="submit,photos,files,audio">Kumpulkan</flux:button>
            </form>
        </x-card>
    @elseif (! $submission && ! $assignment->isOpen())
        <flux:callout icon="lock-closed" color="red" text="Pengumpulan sudah ditutup." />
    @endif
</div>
