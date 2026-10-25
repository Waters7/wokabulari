<?php

use App\Enums\LanguageAspect;
use App\Http\Requests\AssessmentComponentRequest;
use App\Models\LearningSession;
use App\Models\SessionNote;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    public LearningSession $session;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function create(?int $userId = null): void
    {
        $this->authorize('assess', $this->session);
        $this->resetErrorBag();
        $this->form = ['user_id' => $userId ?? $this->session->classroom->participants()->orderBy('name')->value('users.id'), 'aspect' => '', 'note' => '', 'visible_to_guardian' => false];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('assess', $this->session);
        $this->resetErrorBag();

        $validator = Validator::make($this->form, AssessmentComponentRequest::noteRules(), [], ['user_id' => 'peserta', 'note' => 'catatan', 'aspect' => 'aspek']);
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $data = $validator->validated();
        abort_unless($this->session->classroom->participants()->whereKey($data['user_id'])->exists(), 403);

        $this->session->notes()->create([
            'user_id' => $data['user_id'],
            'teacher_id' => auth()->id(),
            'aspect' => $data['aspect'] ?: null,
            'note' => $data['note'],
            'visible_to_guardian' => (bool) ($data['visible_to_guardian'] ?? false),
        ]);

        $this->showForm = false;
        Flux::toast(variant: 'success', text: 'Catatan tersimpan.');
    }

    public function delete(int $id): void
    {
        $note = $this->session->notes()->findOrFail($id);
        abort_unless($note->teacher_id === auth()->id() || auth()->user()->isSuperAdmin(), 403);
        $note->delete();
    }

    public function with(): array
    {
        return [
            'notes' => $this->session->notes()->with(['user', 'teacher'])->get(),
            'participants' => $this->session->classroom->participants()->orderBy('name')->get(),
            'aspects' => LanguageAspect::cases(),
            'canAssess' => auth()->user()->can('assess', $this->session),
        ];
    }
}; ?>

<div>
    @if ($canAssess)
        <flux:button class="mb-4" variant="primary" icon="plus" wire:click="create">Tulis catatan</flux:button>
    @endif

    <div class="space-y-2">
        @forelse ($notes as $note)
            <x-card wire:key="note-{{ $note->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $note->user->name }}</p>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1">
                            @if ($note->aspect)<x-status-badge :status="$note->aspect" />@endif
                            @if ($note->visible_to_guardian)<flux:badge size="sm" color="teal" icon="eye">Dilihat wali</flux:badge>@endif
                            @if ($note->read_at)<flux:badge size="sm" color="zinc">Dibaca {{ tanggal($note->read_at, 'j M') }}</flux:badge>@endif
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm">{{ $note->note }}</p>
                    </div>
                    @if ($note->teacher_id === auth()->id())
                        <x-confirm-button action="delete({{ $note->id }})" title="Hapus catatan ini?" />
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state icon="chat-bubble-left-ellipsis" title="Belum ada catatan" description="Catatan perkembangan untuk peserta tertentu, misalnya pelafalan atau keberanian berbicara." />
        @endforelse
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">Catatan perkembangan</flux:heading>
            <flux:select wire:model="form.user_id" label="Peserta">
                @foreach ($participants as $p)
                    <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="form.aspect" label="Aspek (opsional)">
                <flux:select.option value="">– Umum –</flux:select.option>
                @foreach ($aspects as $aspect)
                    <flux:select.option :value="$aspect->value">{{ $aspect->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="form.note" label="Catatan" rows="4" />
            <flux:switch wire:model="form.visible_to_guardian" label="Boleh dilihat wali" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
