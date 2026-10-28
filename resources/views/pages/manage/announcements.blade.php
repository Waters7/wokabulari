<?php

use App\Actions\Announcements\ManageAnnouncements;
use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Enums\Role;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\Dormitory;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola pengumuman')] class extends Component {
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize('create', Announcement::class);
        $this->resetForm();
    }

    /** @return list<AnnouncementSource> */
    protected function sources(): array
    {
        return AnnouncementSource::allowedFor(auth()->user());
    }

    protected function resetForm(): void
    {
        $source = $this->sources()[0] ?? AnnouncementSource::Team;
        $this->form = [
            'title' => '', 'body' => '', 'source' => $source->value,
            'audience' => AnnouncementAudience::forSource($source)[0]->value,
            'target_id' => null, 'target_role' => Role::Participant->value,
            'is_pinned' => false, 'notify_whatsapp' => false, 'publish' => 'now',
            'publish_at' => now()->addHour()->startOfHour()->format('Y-m-d\TH:i'),
        ];
        $this->form['target_id'] = $this->defaultTarget();
    }

    /** Pilih kelas/asrama pertama yang tersedia agar sasaran tidak kosong. */
    protected function defaultTarget(): ?int
    {
        $source = AnnouncementSource::tryFrom($this->form['source'] ?? '') ?? AnnouncementSource::Team;

        return match ($this->form['audience'] ?? null) {
            'classroom' => $this->classroomOptions($source)->first()?->id,
            'dormitory' => Dormitory::query()->orderBy('name')->value('id'),
            default => null,
        };
    }

    protected function classroomOptions(AnnouncementSource $source)
    {
        $user = auth()->user();

        return Classroom::query()->where('batch_id', Batch::current()?->id)
            ->when($source === AnnouncementSource::Teacher && ! $user->isSuperAdmin(), fn ($q) => $q->taughtBy($user))
            ->orderBy('name')->get();
    }

    public function updatedFormSource(string $value): void
    {
        $allowed = AnnouncementAudience::forSource(AnnouncementSource::from($value));
        if (! in_array(AnnouncementAudience::tryFrom($this->form['audience']), $allowed, true)) {
            $this->form['audience'] = $allowed[0]->value;
        }
        $this->form['target_id'] = $this->defaultTarget();
    }

    public function updatedFormAudience(): void
    {
        $this->form['target_id'] = $this->defaultTarget();
    }

    public function create(): void
    {
        $this->authorize('create', Announcement::class);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $announcement = Announcement::query()->findOrFail($id);
        $this->authorize('update', $announcement);
        $this->resetErrorBag();
        $this->editingId = $id;
        $this->form = [
            'title' => $announcement->title, 'body' => $announcement->body, 'source' => $announcement->source->value,
            'audience' => $announcement->audience->value, 'target_id' => $announcement->target_id,
            'target_role' => $announcement->target_role?->value ?? Role::Participant->value,
            'is_pinned' => $announcement->is_pinned, 'notify_whatsapp' => $announcement->notify_whatsapp,
            'publish' => $announcement->published_at === null ? 'draft' : ($announcement->isScheduled() ? 'schedule' : 'now'),
            'publish_at' => ($announcement->published_at ?? now()->addHour())->format('Y-m-d\TH:i'),
        ];
        $this->showForm = true;
    }

    public function save(ManageAnnouncements $announcements): void
    {
        $announcement = $this->editingId ? Announcement::query()->findOrFail($this->editingId) : null;
        $this->authorize($announcement ? 'update' : 'create', $announcement ?? Announcement::class);
        $this->resetErrorBag();

        $validator = Validator::make($this->form, AnnouncementRequest::rulesFor(), [], AnnouncementRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        try {
            $saved = $announcements->save($validator->validated(), auth()->user(), $announcement);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.audience', $e->getMessage());

            return;
        }

        $this->showForm = false;
        Flux::toast(variant: 'success', text: match (true) {
            $saved->published_at === null => 'Disimpan sebagai draf.',
            $saved->isScheduled() => 'Dijadwalkan terbit '.tanggal_jam($saved->published_at).'.',
            default => 'Pengumuman diterbitkan.',
        });
    }

    public function delete(int $id): void
    {
        $announcement = Announcement::query()->findOrFail($id);
        $this->authorize('delete', $announcement);
        $announcement->delete();
        Flux::toast(text: 'Pengumuman dihapus.');
    }

    public function with(): array
    {
        $user = auth()->user();
        $source = AnnouncementSource::tryFrom($this->form['source'] ?? '') ?? AnnouncementSource::Team;

        return [
            'items' => Announcement::query()->with(['creator', 'classroom', 'dormitory'])->withCount('reads')
                ->when(! $user->hasRole(Role::ProgramAdmin->value) && ! $user->isSuperAdmin(), fn ($q) => $q->where('created_by', $user->id))
                ->latest()->paginate(15),
            'sources' => $this->sources(),
            'audiences' => AnnouncementAudience::forSource($source),
            'classrooms' => $this->classroomOptions($source),
            'dormitories' => Dormitory::query()->orderBy('name')->get(),
            'roles' => [Role::Participant, Role::Guardian, Role::Teacher, Role::Staff],
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Kelola pengumuman" subtitle="Tulis pengumuman untuk peserta, wali, atau rekan kerja.">
        <x-slot:actions>
            <flux:button :href="route('announcements.index')" wire:navigate icon="inbox">Lihat sebagai pembaca</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="create">Pengumuman baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-3">
        @forelse ($items as $item)
            <x-card wire:key="m-{{ $item->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $item->source->accentClasses() }}">
                        <flux:icon :name="$item->source->icon()" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1">
                            <flux:badge size="sm" :color="$item->source->color()">{{ $item->source->label() }}</flux:badge>
                            @if ($item->published_at === null)
                                <flux:badge size="sm">Draf</flux:badge>
                            @elseif ($item->isScheduled())
                                <flux:badge size="sm" color="amber" icon="clock">Terjadwal {{ tanggal_jam($item->published_at) }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">Terbit</flux:badge>
                            @endif
                            @if ($item->is_pinned)
                                <flux:badge size="sm" color="red">Penting</flux:badge>
                            @endif
                        </div>
                        <p class="mt-1 font-semibold">{{ $item->title }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">untuk {{ $item->audienceLabel() }} · {{ $item->creator?->name }} · dibaca {{ $item->reads_count }}</p>
                    </div>
                    <div class="flex shrink-0 flex-col">
                        @can('update', $item)
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $item->id }})" aria-label="Ubah" />
                            <x-confirm-button action="delete({{ $item->id }})" title="Hapus pengumuman ini?" />
                        @endcan
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state icon="megaphone" title="Belum ada pengumuman" description="Buat pengumuman pertama: jadwal piket, perubahan sesi, atau info kegiatan." />
        @endforelse
    </div>
    <div class="mt-4">{{ $items->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah pengumuman' : 'Pengumuman baru' }}</flux:heading>

            @if (count($sources) > 1)
                <flux:radio.group wire:model.live="form.source" label="Atas nama" variant="segmented">
                    @foreach ($sources as $s)
                        <flux:radio :value="$s->value" :label="$s->label()" />
                    @endforeach
                </flux:radio.group>
            @elseif ($sources)
                <p class="text-sm">Atas nama: <flux:badge size="sm" :color="$sources[0]->color()" :icon="$sources[0]->icon()">{{ $sources[0]->label() }}</flux:badge></p>
            @endif

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:select wire:model.live="form.audience" label="Sasaran">
                    @foreach ($audiences as $a)
                        <flux:select.option :value="$a->value">{{ $a->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if (($form['audience'] ?? '') === 'classroom')
                    <flux:select wire:model="form.target_id" label="Kelas" placeholder="Pilih kelas">
                        @foreach ($classrooms as $c)
                            <flux:select.option :value="$c->id">{{ $c->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif (($form['audience'] ?? '') === 'dormitory')
                    <flux:select wire:model="form.target_id" label="Asrama" placeholder="Pilih asrama">
                        @foreach ($dormitories as $d)
                            <flux:select.option :value="$d->id">{{ $d->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @elseif (($form['audience'] ?? '') === 'role')
                    <flux:select wire:model="form.target_role" label="Peran">
                        @foreach ($roles as $r)
                            <flux:select.option :value="$r->value">{{ $r->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
            <flux:error name="form.target_id" />

            <flux:input wire:model="form.title" label="Judul" placeholder="Jadwal piket kebersihan minggu ini" />
            <flux:textarea wire:model="form.body" label="Isi" rows="5" />

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:switch wire:model="form.is_pinned" label="Tandai penting" description="Tampil paling atas." />
                <flux:switch wire:model="form.notify_whatsapp" label="Kirim juga via WhatsApp" />
            </div>

            <flux:radio.group wire:model.live="form.publish" label="Terbitkan" variant="segmented">
                <flux:radio value="now" label="Sekarang" />
                <flux:radio value="schedule" label="Jadwalkan" />
                <flux:radio value="draft" label="Draf" />
            </flux:radio.group>
            @if (($form['publish'] ?? '') === 'schedule')
                <flux:input wire:model="form.publish_at" type="datetime-local" label="Waktu terbit" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
