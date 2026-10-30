<?php

use App\Actions\Sessions\EndSession;
use App\Actions\Sessions\ManageSessionCode;
use App\Actions\Sessions\StoreSessionDocumentation;
use App\Enums\DocumentationSource;
use App\Enums\SessionPhase;
use App\Enums\SessionStatus;
use App\Http\Requests\SessionDocumentationRequest;
use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use App\Services\SessionDocumentationRule;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Detail sesi')] class extends Component {
    use WithFileUploads;

    public LearningSession $session;

    #[Url]
    public string $tab = 'kehadiran';

    /** Dokumentasi kelas yang sedang diunggah (foto kamera atau file). */
    public $docFile = null;

    public string $docSource = 'camera';

    public string $docPhase = 'start';

    /** Setelah dokumentasi tersimpan, langsung mulai/selesaikan sesi. */
    public bool $docThenAct = false;

    public bool $showDoc = false;

    public function mount(LearningSession $session): void
    {
        $this->authorize('view', $session);
        $this->session = $session->load(['classroom.language', 'teacher']);
    }

    public function start(ManageSessionCode $codes, SessionDocumentationRule $rule): void
    {
        $this->authorize('update', $this->session);

        if ($this->session->status === SessionStatus::Scheduled && $rule->missing($this->session, SessionPhase::Start)) {
            $this->openDoc(SessionPhase::Start->value, true);

            return;
        }

        try {
            $codes->start($this->session, auth()->user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->session->refresh();
        $this->tab = 'kehadiran';
        $this->dispatch('session-updated');
        Flux::toast(variant: 'success', text: 'Sesi dimulai. Tampilkan kode ke peserta!');
    }

    public function end(EndSession $end, SessionDocumentationRule $rule): void
    {
        $this->authorize('update', $this->session);

        if ($this->session->status !== SessionStatus::Ongoing) {
            return;
        }

        if ($rule->missing($this->session, SessionPhase::End)) {
            $this->openDoc(SessionPhase::End->value, true);

            return;
        }

        $count = $end->handle($this->session, auth()->user());
        $this->session->refresh();
        $this->dispatch('session-updated');
        Flux::toast(variant: 'success', text: "Sesi selesai. {$count} peserta yang belum input kode ditandai otomatis.");
    }

    public function openDoc(string $phase, bool $act = false): void
    {
        $this->authorize('create', [SessionDocumentation::class, $this->session]);
        $this->docPhase = (SessionPhase::tryFrom($phase) ?? SessionPhase::Start)->value;
        $this->docThenAct = $act;
        $this->docSource = DocumentationSource::Camera->value;
        $this->reset('docFile');
        $this->resetErrorBag();
        $this->showDoc = true;
    }

    public function submitDoc(StoreSessionDocumentation $store, ManageSessionCode $codes, EndSession $end, SessionDocumentationRule $rule): void
    {
        $this->authorize('create', [SessionDocumentation::class, $this->session]);
        $this->resetErrorBag();

        $validator = Validator::make(['file' => $this->docFile, 'source' => $this->docSource, 'phase' => $this->docPhase],
            SessionDocumentationRequest::rulesFor(), SessionDocumentationRequest::messagesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(['docFile' => $validator->errors()->first()]);
        }

        $phase = SessionPhase::from($this->docPhase);

        try {
            $store->handle($this->session, auth()->user(), $phase, $this->docFile, DocumentationSource::from($this->docSource));
        } catch (\InvalidArgumentException $e) {
            $this->addError('docFile', $e->getMessage());

            return;
        }

        $this->reset('docFile');
        $this->showDoc = false;

        if (! $this->docThenAct) {
            Flux::toast(variant: 'success', text: 'Dokumentasi kelas tersimpan.');

            return;
        }

        $phase === SessionPhase::Start ? $this->start($codes, $rule) : $this->end($end, $rule);
    }

    public function deleteDoc(int $id): void
    {
        $doc = $this->session->documentations()->findOrFail($id);
        $this->authorize('delete', $doc);
        $doc->delete();
        Flux::toast(text: 'Dokumentasi dihapus.');
    }

    public function with(): array
    {
        $rule = app(SessionDocumentationRule::class);
        $tabs = ['kehadiran' => 'Kehadiran', 'materi' => 'Materi'];

        // Tab tambahan dari fase berikutnya (penilaian, catatan) tampil bila komponennya ada.
        foreach (['penilaian' => ['Penilaian', 'session.assessment-panel'], 'catatan' => ['Catatan', 'session.notes-panel']] as $key => [$label, $component]) {
            if (view()->exists('livewire.'.$component)) {
                $tabs[$key] = $label;
            }
        }

        $me = auth()->user();
        $myCode = $this->session->codes()->where('generated_by', $me->id)->exists();
        $canSeeDocs = $me->can('viewAny', [SessionDocumentation::class, $this->session]);

        return [
            'tabs' => $tabs,
            'canManage' => $me->can('update', $this->session),
            // Kehadiran pengajar: membuat kode sesi + ada peserta yang hadir.
            'teachingStatus' => $me->isTeacher() ? match (true) {
                $myCode && \App\Services\Reports\TeachingAnalytics::isPresent($this->session, $me) => \App\Enums\TeachingStatus::Present,
                $myCode => \App\Enums\TeachingStatus::NoParticipants,
                default => null,
            } : null,
            'canSeeDocs' => $canSeeDocs,
            'docs' => $canSeeDocs ? $this->session->documentations()->with('uploader')->get()->groupBy(fn ($d) => $d->phase->value) : collect(),
            'docRequired' => $rule->required($this->session),
            'needsStartDoc' => $this->session->status === SessionStatus::Scheduled && $rule->missing($this->session, SessionPhase::Start),
            'needsEndDoc' => $this->session->status === SessionStatus::Ongoing && $rule->missing($this->session, SessionPhase::End),
            'maxDocs' => StoreSessionDocumentation::MAX_PER_PHASE,
        ];
    }
}; ?>

<div>
    <x-page-header :title="$session->topic" :back="route('teach.sessions.index')"
                   :subtitle="tanggal_panjang($session->date).' · '.$session->timeRange().' · '.$session->classroom->label()">
        <x-slot:actions>
            <x-status-badge :status="$session->status" size="lg" />
            @if ($canManage)
                @if ($session->status === SessionStatus::Scheduled)
                    <flux:button variant="primary" icon="{{ $needsStartDoc ? 'camera' : 'play' }}" wire:click="start">Mulai sesi</flux:button>
                @elseif ($session->status === SessionStatus::Ongoing)
                    @if ($needsEndDoc)
                        <flux:button variant="danger" icon="camera" wire:click="openDoc('end', true)">Selesai sesi</flux:button>
                    @else
                        <x-confirm-button action="end" label="Selesai sesi" icon="stop" variant="danger"
                                          title="Selesaikan sesi?" message="Peserta yang belum input kode akan ditandai alpa, kecuali punya izin/sakit yang disetujui." confirmLabel="Ya, selesai" size="base" />
                    @endif
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($teachingStatus)
        <flux:callout class="mb-4" data-test="teaching-status"
                      :icon="$teachingStatus === \App\Enums\TeachingStatus::Present ? 'check-circle' : 'clock'"
                      :color="$teachingStatus === \App\Enums\TeachingStatus::Present ? 'green' : 'amber'"
                      :text="$teachingStatus === \App\Enums\TeachingStatus::Present ? 'Kehadiran mengajar ngana di sesi ini sudah tercatat.' : 'Kode sudah dibuat. Kehadiran mengajar tercatat setelah peserta pertama masuk.'" />
    @endif

    @if ($canManage && $needsStartDoc)
        <flux:callout class="mb-4" icon="camera" color="amber" data-test="doc-reminder" heading="Foto dokumentasi saat mulai sesi"
                      text="Tekan Mulai sesi, lalu ambil foto bersama kelas (selfie) atau unggah file dokumentasi. Saat Selesai sesi juga diminta foto/file akhir sesi." />
    @endif

    @if ($session->objectives)
        <flux:callout icon="light-bulb" class="mb-4" heading="Tujuan pembelajaran" :text="$session->objectives" />
    @endif

    @if ($canSeeDocs && ($docRequired || $docs->isNotEmpty() || ($canManage && $session->status !== SessionStatus::Scheduled)))
        <x-card class="mb-4" title="Dokumentasi kelas">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (SessionPhase::cases() as $phase)
                    @php
                        $items = $docs->get($phase->value, collect());
                        $reached = $phase === SessionPhase::Start ? $session->status !== SessionStatus::Scheduled : $session->status === SessionStatus::Completed;
                    @endphp
                    <div wire:key="docs-{{ $phase->value }}">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">{{ $phase->label() }}</p>
                            @if ($items->isEmpty())
                                <span @class(['text-xs font-semibold', 'text-rica' => $docRequired && $reached, 'text-hutan/50 dark:text-santan/50' => ! ($docRequired && $reached)])>
                                    {{ $docRequired && $reached ? 'Belum ada dokumentasi' : 'Belum ada' }}
                                </span>
                            @else
                                <span class="text-xs text-hutan/50 dark:text-santan/50">{{ $items->count() }} file</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($items as $doc)
                                <div class="group relative" wire:key="doc-{{ $doc->id }}">
                                    <a href="{{ $doc->url() }}" target="_blank" rel="noopener"
                                       class="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-zinc-50 ring-1 ring-zinc-200 dark:bg-hutan-900 dark:ring-hutan-800"
                                       title="{{ $doc->source->label() }} · {{ $doc->created_at->format('H:i') }}{{ $doc->uploader ? ' · '.$doc->uploader->name : '' }}">
                                        @if ($doc->isImage())
                                            <img src="{{ $doc->url() }}" alt="Dokumentasi {{ strtolower($phase->label()) }}" class="size-full object-cover" loading="lazy">
                                        @else
                                            <span class="flex flex-col items-center gap-1 px-1 text-center text-[11px] text-hutan/70 dark:text-santan/70">
                                                <flux:icon.document-text class="size-7" />
                                                <span class="line-clamp-2 break-all">{{ $doc->original_name ?? 'File' }}</span>
                                            </span>
                                        @endif
                                    </a>
                                    <span class="pointer-events-none absolute inset-x-1 bottom-1 rounded-md bg-hutan/70 px-1 text-center text-[10px] font-semibold text-white">{{ $doc->created_at->format('H:i') }}</span>
                                    @can('delete', $doc)
                                        <button type="button" wire:click="deleteDoc({{ $doc->id }})" wire:confirm="Hapus dokumentasi ini?"
                                                class="absolute -right-1.5 -top-1.5 flex size-6 items-center justify-center rounded-full bg-white text-rica shadow ring-1 ring-zinc-200 dark:bg-hutan-900 dark:ring-hutan-800"
                                                aria-label="Hapus dokumentasi">
                                            <flux:icon.x-mark variant="micro" />
                                        </button>
                                    @endcan
                                </div>
                            @endforeach
                            @if ($canManage && $items->count() < $maxDocs && $session->status !== SessionStatus::Scheduled)
                                <button type="button" wire:click="openDoc('{{ $phase->value }}')"
                                        class="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-woka-200 text-xs font-semibold text-woka hover:bg-woka-50 dark:border-hutan-800 dark:text-woka-light dark:hover:bg-hutan-800">
                                    <flux:icon.plus class="size-5" /> Tambah
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['min-h-11 flex-1 whitespace-nowrap rounded-lg px-2.5 text-sm font-semibold transition sm:px-4', 'bg-woka text-white' => $tab === $key, 'text-hutan/70 dark:text-santan/70 hover:bg-woka-50 dark:text-santan/70 dark:hover:bg-hutan-800' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @switch($tab)
        @case('materi')
            <livewire:session.materials-panel :session="$session" :key="'materials-'.$session->id" />
            @break
        @case('penilaian')
            <livewire:session.assessment-panel :session="$session" :key="'assessment-'.$session->id" />
            @break
        @case('catatan')
            <livewire:session.notes-panel :session="$session" :key="'notes-'.$session->id" />
            @break
        @default
            <livewire:session.attendance-panel :session="$session" :key="'attendance-'.$session->id.'-'.$session->status->value" />
    @endswitch

    @if ($canManage)
        <flux:modal wire:model.self="showDoc" class="w-full max-w-md">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $docPhase === 'end' ? 'Dokumentasi akhir sesi' : 'Dokumentasi mulai sesi' }}</flux:heading>
                    <flux:text class="mt-1">Ambil foto bersama kelas (selfie) atau unggah file dokumentasi (foto/PDF, maks. 10 MB).</flux:text>
                </div>

                <flux:radio.group wire:model.live="docSource" variant="segmented" aria-label="Cara dokumentasi">
                    <flux:radio value="camera" icon="camera">Kamera</flux:radio>
                    <flux:radio value="upload" icon="arrow-up-tray">Unggah file</flux:radio>
                </flux:radio.group>

                @if ($showDoc && $docSource === 'camera')
                    <div x-data="classCamera('docFile')" wire:ignore wire:key="cam-{{ $docPhase }}">
                        <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-hutan">
                            <video x-ref="video" playsinline muted class="size-full object-cover" :class="facing === 'user' ? '-scale-x-100' : ''" x-show="state === 'live' || state === 'uploading'"></video>
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-santan" x-show="state !== 'live' && state !== 'uploading'">
                                <flux:icon.camera class="size-12 text-kunyit" x-show="state === 'idle' || state === 'starting'" />
                                <flux:icon.check-circle class="size-12 text-woka-light" x-show="state === 'done'" />
                                <flux:icon.exclamation-triangle class="size-12 text-rica-100" x-show="state === 'error'" />
                                <p class="text-sm" x-show="state === 'idle'">Arahkan kamera ke kelas, pastikan pengajar dan peserta terlihat.</p>
                                <p class="text-sm" x-show="state === 'starting'">Membuka kamera…</p>
                                <p class="text-sm" x-show="state === 'done'">Foto siap. Periksa pratinjau di bawah.</p>
                                <p class="text-sm" x-show="state === 'error'" x-text="message"></p>
                            </div>
                            <div class="absolute inset-0 flex items-center justify-center bg-hutan/50 text-sm font-semibold text-white" x-show="state === 'uploading'">Mengunggah foto…</div>
                        </div>
                        <div class="mt-2 grid grid-cols-[1fr_auto] gap-2">
                            <flux:button icon="camera" class="w-full" x-show="state === 'idle' || state === 'error'" x-on:click="open()">Buka kamera</flux:button>
                            <flux:button variant="primary" icon="camera" class="w-full" x-show="state === 'live'" x-on:click="snap()">Ambil foto</flux:button>
                            <flux:button icon="arrow-path" class="w-full" x-show="state === 'done'" x-on:click="retake()">Ambil ulang</flux:button>
                            <flux:button icon="arrows-right-left" x-show="state === 'live'" x-on:click="flip()" aria-label="Ganti kamera depan/belakang" />
                        </div>
                    </div>
                @elseif ($showDoc)
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-woka-200 px-4 py-8 text-center hover:bg-woka-50 dark:border-hutan-800 dark:hover:bg-hutan-800">
                        <flux:icon.arrow-up-tray class="size-8 text-woka dark:text-woka-light" />
                        <span class="text-sm font-semibold">Pilih foto atau file dokumentasi</span>
                        <span class="text-xs text-hutan/60 dark:text-santan/60">JPG, PNG, WEBP, HEIC, atau PDF · maks. 10 MB</span>
                        <input type="file" wire:model="docFile" accept="image/*,application/pdf" class="sr-only">
                    </label>
                @endif

                <div wire:loading wire:target="docFile" class="text-sm text-woka">Mengunggah…</div>
                @if ($docFile)
                    <div class="flex items-center gap-3 rounded-xl bg-woka-50 p-2 dark:bg-hutan-800" data-test="doc-preview">
                        @if ($docFile->isPreviewable())
                            <img src="{{ $docFile->temporaryUrl() }}" alt="Pratinjau dokumentasi" class="size-16 shrink-0 rounded-lg object-cover">
                        @else
                            <flux:icon.document-text class="size-10 shrink-0 text-woka" />
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-semibold">{{ $docSource === 'camera' ? 'Foto kamera' : $docFile->getClientOriginalName() }}</p>
                            <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $docFile->getSize() >= 1048576 ? angka($docFile->getSize() / 1048576, 1).' MB' : angka(max(1, $docFile->getSize() / 1024)).' KB' }} · siap disimpan</p>
                        </div>
                    </div>
                @endif
                @error('docFile')
                    <p class="text-sm font-semibold text-rica">{{ $message }}</p>
                @enderror

                @if ($docThenAct && $docPhase === 'end')
                    <flux:callout icon="exclamation-triangle" color="amber" text="Setelah disimpan, sesi selesai. Peserta yang belum input kode ditandai alpa, kecuali punya izin/sakit yang disetujui." />
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button :variant="$docThenAct && $docPhase === 'end' ? 'danger' : 'primary'" wire:click="submitDoc" wire:loading.attr="disabled" wire:target="docFile,submitDoc">
                        {{ $docThenAct ? ($docPhase === 'end' ? 'Simpan & selesaikan sesi' : 'Simpan & mulai sesi') : 'Simpan dokumentasi' }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
