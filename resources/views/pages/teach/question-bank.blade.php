<?php

use App\Actions\Quizzes\ImportQuestions;
use App\Actions\Quizzes\SaveQuestion;
use App\Enums\QuestionType;
use App\Http\Requests\QuizRequest;
use App\Imports\ParticipantRowsImport;
use App\Models\Question;
use App\Models\QuestionBank;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Soal')] class extends Component {
    use WithFileUploads;

    public QuestionBank $bank;

    #[Url]
    public string $type = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public $media = null;

    public bool $showImport = false;

    public $importFile = null;

    public ?array $importResult = null;

    public function mount(QuestionBank $bank): void
    {
        $this->authorize('view', $bank);
        $this->bank = $bank->load('language');
        $this->resetForm();
    }

    protected function resetForm(?string $type = null): void
    {
        $this->form = [
            'type' => $type ?? QuestionType::MultipleChoice->value,
            'body' => '',
            'reading' => '',
            'points' => 1,
            'explanation' => '',
            'options' => [['body' => '', 'reading' => '', 'is_correct' => true, 'match_key' => ''], ['body' => '', 'reading' => '', 'is_correct' => false, 'match_key' => ''], ['body' => '', 'reading' => '', 'is_correct' => false, 'match_key' => ''], ['body' => '', 'reading' => '', 'is_correct' => false, 'match_key' => '']],
            'correct_index' => 0,
            'accepted_answers' => [''],
            'correct_bool' => '1',
            'correct_tone' => 1,
        ];
        $this->media = null;
    }

    public function create(): void
    {
        $this->authorize('update', $this->bank);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('update', $this->bank);
        $question = $this->bank->questions()->with('options')->findOrFail($id);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->resetForm($question->type->value);

        $this->form = array_merge($this->form, [
            'body' => $question->body,
            'reading' => $question->reading ?? '',
            'points' => $question->points,
            'explanation' => $question->explanation ?? '',
        ]);

        $options = $question->options;
        match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::Listening => $this->form = array_merge($this->form, [
                'options' => $options->map(fn ($o) => ['body' => $o->body, 'reading' => $o->reading ?? '', 'is_correct' => $o->is_correct, 'match_key' => ''])->all(),
                'correct_index' => max(0, (int) $options->search(fn ($o) => $o->is_correct)),
            ]),
            QuestionType::Matching => $this->form['options'] = $options->map(fn ($o) => ['body' => $o->body, 'reading' => $o->reading ?? '', 'is_correct' => true, 'match_key' => $o->match_key])->all(),
            QuestionType::ShortAnswer => $this->form['accepted_answers'] = $options->pluck('body')->all(),
            QuestionType::TrueFalse => $this->form['correct_bool'] = $options->firstWhere('body', 'Benar')?->is_correct ? '1' : '0',
            QuestionType::Tone => $this->form['correct_tone'] = (int) $options->firstWhere('is_correct', true)?->body,
            default => null,
        };

        $this->showForm = true;
    }

    public function addOption(): void
    {
        $this->form['options'][] = ['body' => '', 'reading' => '', 'is_correct' => false, 'match_key' => ''];
    }

    public function removeOption(int $index): void
    {
        unset($this->form['options'][$index]);
        $this->form['options'] = array_values($this->form['options']);
        $this->form['correct_index'] = min((int) $this->form['correct_index'], max(0, count($this->form['options']) - 1));
    }

    public function addAccepted(): void
    {
        $this->form['accepted_answers'][] = '';
    }

    public function save(SaveQuestion $save): void
    {
        $this->authorize('update', $this->bank);
        $this->resetErrorBag();

        // Opsi benar pilihan ganda ditentukan oleh radio "correct_index".
        $data = $this->form;
        foreach ($data['options'] as $i => $option) {
            $data['options'][$i]['is_correct'] = $data['type'] === QuestionType::Matching->value || $i === (int) $data['correct_index'];
        }

        $validator = Validator::make($data + ['media' => $this->media], QuizRequest::questionRules(), [], ['body' => 'soal', 'points' => 'poin', 'media' => 'berkas audio/gambar']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => [$k === 'media' ? 'media' : 'form.'.$k => $m])->all());
        }

        try {
            $save->handle($this->bank, $data, $this->editingId ? $this->bank->questions()->findOrFail($this->editingId) : null, $this->media);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.options', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Soal tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->authorize('update', $this->bank);
        $question = $this->bank->questions()->findOrFail($id);

        if ($question->quizzes()->exists()) {
            Flux::toast(variant: 'danger', text: 'Soal masih dipakai quiz.');

            return;
        }

        $question->delete();
        Flux::toast(text: 'Soal dihapus.');
    }

    public function import(ImportQuestions $import): void
    {
        $this->authorize('update', $this->bank);
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']], [], ['importFile' => 'berkas']);

        $reader = new ParticipantRowsImport;
        Excel::import($reader, $this->importFile->getRealPath(), null, strtolower($this->importFile->getClientOriginalExtension()) === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);

        $this->importResult = $import->handle($this->bank, $reader->rows);
        $this->importFile = null;
        Flux::toast(variant: 'success', text: $this->importResult['created'].' soal diimport.');
    }

    public function with(): array
    {
        return [
            'questions' => $this->bank->questions()->with('options')->withCount('quizzes')
                ->when($this->type, fn ($q) => $q->where('type', $this->type))->get(),
            'types' => QuestionType::cases(),
            'tones' => SaveQuestion::TONES,
            'canEdit' => auth()->user()->can('update', $this->bank),
            'isCjk' => $this->bank->language->isCjk(),
        ];
    }
}; ?>

<div>
    @if ($isCjk)<x-cjk-fonts />@endif

    <x-page-header :title="$bank->name" :back="route('teach.questions.index')"
                   :subtitle="$bank->language->name.($bank->level ? ' · '.$bank->level : '').' · '.$questions->count().' soal'">
        @if ($canEdit)
            <x-slot:actions>
                <flux:button icon="arrow-up-tray" wire:click="$set('showImport', true)">Import Excel</flux:button>
                <flux:button variant="primary" icon="plus" wire:click="create">Soal baru</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <flux:select wire:model.live="type" class="mb-4 max-w-xs">
        <flux:select.option value="">Semua tipe</flux:select.option>
        @foreach ($types as $t)
            <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <div class="space-y-2">
        @forelse ($questions as $index => $question)
            <x-card wire:key="q-{{ $question->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="font-semibold text-hutan/60">#{{ $index + 1 }}</span>
                            <x-status-badge :status="$question->type" />
                            <span class="text-hutan/60">{{ angka($question->points, 1) }} poin</span>
                            @if ($question->quizzes_count)<flux:badge size="sm">Dipakai {{ $question->quizzes_count }} quiz</flux:badge>@endif
                            @if ($question->media_path)<flux:badge size="sm" icon="musical-note">Media</flux:badge>@endif
                        </div>
                        <x-reading-text :text="$question->body" :reading="$question->reading" as="p" class="whitespace-pre-line font-semibold" />
                        @if ($question->options->isNotEmpty())
                            <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                                @foreach ($question->options as $option)
                                    <li @class(['rounded-lg px-2 py-1', 'bg-woka-50 font-semibold text-woka-700 dark:bg-hutan-800 dark:text-woka-light' => $option->is_correct && $question->type !== QuestionType::Matching, 'bg-santan dark:bg-hutan-950' => ! $option->is_correct || $question->type === QuestionType::Matching])>
                                        {{ $question->type === QuestionType::Tone ? $option->reading : $option->body }}
                                        @if ($question->type === QuestionType::Matching) → {{ $option->match_key }} @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @if ($canEdit)
                        <div class="flex shrink-0">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $question->id }})" aria-label="Ubah" />
                            <x-confirm-button action="delete({{ $question->id }})" title="Hapus soal ini?" />
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state icon="rectangle-stack" title="Belum ada soal" description="Tambah satu per satu atau import dari Excel." />
        @endforelse
    </div>

    {{-- Form soal --}}
    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah soal' : 'Soal baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-3">
                <flux:select wire:model.live="form.type" label="Tipe" class="sm:col-span-2">
                    @foreach ($types as $t)
                        <flux:select.option :value="$t->value">{{ $t->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.points" type="number" step="0.5" min="0.5" label="Poin" />
            </div>
            <flux:textarea wire:model="form.body" label="Pertanyaan" rows="3" :placeholder="$isCjk ? 'Contoh: 你好吗？' : 'Tulis pertanyaan…'" />
            <flux:input wire:model="form.reading" :label="'Bacaan ('.($bank->language->reading_label ?? 'opsional').')'" placeholder="nǐ hǎo ma? / konnichiwa" />

            @if (in_array($form['type'], ['listening', 'speaking'], true))
                <div>
                    <flux:input type="file" wire:model="media" accept="audio/*,image/*" :label="$form['type'] === 'listening' ? 'Audio soal (wajib)' : 'Audio/gambar contoh (opsional)'" />
                    <div wire:loading wire:target="media" class="mt-1 text-xs text-woka">Mengunggah…</div>
                    <flux:error name="media" />
                </div>
            @endif

            @switch($form['type'])
                @case('multiple_choice')
                @case('listening')
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Opsi jawaban (pilih yang benar)</p>
                        @foreach ($form['options'] as $i => $option)
                            <div class="flex items-center gap-2" wire:key="opt-{{ $i }}">
                                <input type="radio" wire:model="form.correct_index" value="{{ $i }}" class="size-5 shrink-0 accent-woka" aria-label="Jawaban benar">
                                <flux:input wire:model="form.options.{{ $i }}.body" placeholder="Opsi {{ chr(65 + $i) }}" class="flex-1" />
                                <flux:input wire:model="form.options.{{ $i }}.reading" placeholder="Bacaan" class="w-28" />
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeOption({{ $i }})" aria-label="Hapus opsi" />
                            </div>
                        @endforeach
                        <flux:button size="sm" icon="plus" wire:click="addOption">Opsi</flux:button>
                    </div>
                    @break
                @case('true_false')
                    <flux:radio.group wire:model="form.correct_bool" label="Jawaban benar" variant="segmented">
                        <flux:radio value="1">Benar</flux:radio>
                        <flux:radio value="0">Salah</flux:radio>
                    </flux:radio.group>
                    @break
                @case('tone')
                    <flux:select wire:model="form.correct_tone" label="Nada yang benar">
                        @foreach ($tones as $tone => $label)
                            <flux:select.option :value="$tone">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text class="text-xs">Tulis hanzi di kolom pertanyaan dan pinyin tanpa tanda nada di kolom bacaan (mis. "ma").</flux:text>
                    @break
                @case('short_answer')
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Jawaban yang diterima (huruf besar/kecil & tanda baca akhir diabaikan)</p>
                        @foreach ($form['accepted_answers'] as $i => $accepted)
                            <flux:input wire:model="form.accepted_answers.{{ $i }}" wire:key="acc-{{ $i }}" placeholder="Jawaban {{ $i + 1 }}" />
                        @endforeach
                        <flux:button size="sm" icon="plus" wire:click="addAccepted">Jawaban lain</flux:button>
                    </div>
                    @break
                @case('matching')
                    <div class="space-y-2">
                        <p class="text-sm font-semibold">Pasangan (kiri → kanan)</p>
                        @foreach ($form['options'] as $i => $option)
                            <div class="flex items-center gap-2" wire:key="pair-{{ $i }}">
                                <flux:input wire:model="form.options.{{ $i }}.body" placeholder="Kiri, mis. 医生" class="flex-1" />
                                <span aria-hidden="true">→</span>
                                <flux:input wire:model="form.options.{{ $i }}.match_key" placeholder="Kanan, mis. dokter" class="flex-1" />
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeOption({{ $i }})" aria-label="Hapus pasangan" />
                            </div>
                        @endforeach
                        <flux:button size="sm" icon="plus" wire:click="addOption">Pasangan</flux:button>
                    </div>
                    @break
                @default
                    <flux:callout icon="information-circle" text="Soal esai/berbicara dinilai pengajar lewat menu Perlu Dinilai." />
            @endswitch
            <flux:error name="form.options" />

            <flux:textarea wire:model="form.explanation" label="Pembahasan (tampil setelah hasil dirilis)" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="media,save">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Import --}}
    <flux:modal wire:model.self="showImport" class="w-full max-w-lg">
        <form wire:submit="import" class="space-y-4">
            <flux:heading size="lg">Import soal dari Excel</flux:heading>
            <flux:text>
                Kolom: <strong>tipe, soal, bacaan, opsi_a…opsi_e, jawaban, poin, pembahasan</strong>.
                Jawaban: huruf (pilihan ganda), benar/salah, nada 1–4, isian dipisah "|", atau pasangan "kiri=kanan|kiri=kanan".
            </flux:text>
            <flux:button size="sm" icon="arrow-down-tray" :href="route('teach.questions.template')">Unduh template</flux:button>
            <flux:input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" label="Berkas" />
            @if ($importResult)
                <flux:callout icon="check-circle" color="green" :heading="$importResult['created'].' soal diimport'" />
                @foreach ($importResult['errors'] as $error)
                    <p class="text-sm text-rica">{{ $error }}</p>
                @endforeach
            @endif
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Import</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
