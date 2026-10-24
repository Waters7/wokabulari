<?php

use App\Actions\Participants\ImportParticipants;
use App\Actions\Participants\SaveParticipant;
use App\Enums\Gender;
use App\Http\Requests\ParticipantRequest;
use App\Imports\ParticipantRowsImport;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\User;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Peserta')] class extends Component {
    use WithCrudModal, WithFileUploads;

    #[Url]
    public ?int $classroom = null;

    #[Url]
    public string $status = '';

    public bool $showImport = false;

    public $importFile = null;

    public ?int $importClassroom = null;

    /** @var array<string, mixed>|null */
    public ?array $importResult = null;

    protected function modelClass(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => '', 'username' => '', 'email' => '', 'phone' => '', 'is_active' => true,
            'classroom_id' => $this->classroom, 'school_name' => '', 'major' => '', 'gender' => '',
            'birth_date' => '', 'allergies' => '', 'medical_history' => '', 'emergency_contact_name' => '',
            'emergency_contact_phone' => '', 'target_exam' => '', 'target_score' => '',
        ];
    }

    protected function formRules(): array
    {
        $user = $this->editingId ? User::find($this->editingId) : null;
        $rules = ParticipantRequest::rulesFor($user);
        // Nomor HP dinormalisasi sebelum dicek keunikannya
        $rules['phone'] = ['nullable', 'string', 'max:20'];

        return $rules;
    }

    protected function labels(): array
    {
        return ['name' => 'nama', 'username' => 'NIS', 'phone' => 'nomor HP', 'classroom_id' => 'kelas', 'school_name' => 'asal sekolah'];
    }

    protected function fillFrom(Model $model): array
    {
        $profile = $model->profile;
        $batch = Batch::current();

        return [
            'name' => $model->name,
            'username' => $model->username,
            'email' => $model->email,
            'phone' => $model->phone ? Phone::display($model->phone) : '',
            'is_active' => $model->is_active,
            'classroom_id' => $model->classrooms()->when($batch, fn ($q) => $q->where('batch_id', $batch->id))->value('classrooms.id'),
            'school_name' => $profile?->school_name,
            'major' => $profile?->major,
            'gender' => $profile?->gender?->value ?? '',
            'birth_date' => $profile?->birth_date?->toDateString() ?? '',
            'allergies' => $profile?->allergies,
            'medical_history' => $profile?->medical_history,
            'emergency_contact_name' => $profile?->emergency_contact_name,
            'emergency_contact_phone' => $profile?->emergency_contact_phone ? Phone::display($profile->emergency_contact_phone) : '',
            'target_exam' => $profile?->target_exam,
            'target_score' => $profile?->target_score,
        ];
    }

    protected function persist(array $data, ?Model $model): void
    {
        $phone = Phone::normalize($data['phone'] ?? null);

        if ($phone && User::query()->where('phone', $phone)->when($model, fn ($q) => $q->whereKeyNot($model->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.phone' => 'Nomor HP sudah dipakai akun lain.']);
        }

        try {
            app(SaveParticipant::class)->handle($data, $model);
        } catch (InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.name' => $e->getMessage()]);
        }
    }

    protected function findModel(int $id): Model
    {
        return User::query()->participants()->findOrFail($id);
    }

    public function resetPassword(int $id): void
    {
        $user = $this->findModel($id);
        $this->authorize('update', $user);

        try {
            app(SaveParticipant::class)->resetPassword($user);
            Flux::toast(variant: 'success', text: "Kata sandi {$user->name} dikembalikan ke sandi awal.");
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openImport(): void
    {
        $this->authorize('create', User::class);
        $this->reset('importFile', 'importResult');
        $this->importClassroom = $this->classroom;
        $this->showImport = true;
    }

    public function import(ImportParticipants $importer): void
    {
        $this->authorize('create', User::class);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'importClassroom' => ['nullable', 'exists:classrooms,id'],
        ], [], ['importFile' => 'berkas', 'importClassroom' => 'kelas']);

        $reader = new ParticipantRowsImport;
        Excel::import($reader, $this->importFile->getRealPath(), null, $this->readerType());

        try {
            $result = $importer->handle($reader->rows, $this->importClassroom ? Classroom::find($this->importClassroom) : null);
        } catch (InvalidArgumentException $e) {
            $this->addError('importFile', $e->getMessage());

            return;
        }

        activity('peserta')->causedBy(auth()->user())->withProperties(['ringkasan' => $result->summary()])->log('Import peserta');

        $this->importResult = [
            'summary' => $result->summary(),
            'warnings' => $result->warnings,
            'notes' => $result->notes,
        ];
        $this->importFile = null;

        Flux::toast(variant: 'success', text: $result->summary());
    }

    protected function readerType(): ?string
    {
        return match (strtolower($this->importFile->getClientOriginalExtension())) {
            'csv', 'txt' => \Maatwebsite\Excel\Excel::CSV,
            'xls' => \Maatwebsite\Excel\Excel::XLS,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };
    }

    public function updatingClassroom(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $batch = Batch::current();

        return [
            'participants' => User::query()
                ->participants()
                ->with(['profile', 'classrooms' => fn ($q) => $q->when($batch, fn ($q) => $q->where('batch_id', $batch->id))])
                ->search($this->search)
                ->when($this->classroom, fn (Builder $q) => $q->whereHas('classrooms', fn ($q) => $q->where('classrooms.id', $this->classroom)))
                ->when($this->status === 'no_class', fn (Builder $q) => $q->whereDoesntHave('classrooms'))
                ->when($this->status === 'no_face', fn (Builder $q) => $q->whereDoesntHave('profile', fn ($q) => $q->whereNotNull('face_enrolled_at')))
                ->when($this->status === 'first_login', fn (Builder $q) => $q->where('must_change_password', true))
                ->when($this->status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
                ->orderBy('name')
                ->paginate(25),
            'classrooms' => Classroom::query()->with('language')->when($batch, fn ($q) => $q->where('batch_id', $batch->id))->orderBy('name')->get(),
            'genders' => Gender::cases(),
            'canManage' => auth()->user()->can('participant.manage'),
        ];
    }
}; ?>

<div>
    <x-page-header title="Peserta" subtitle="Data peserta, kelas, akun login, dan status pendaftaran wajah.">
        @if ($canManage)
            <x-slot:actions>
                <flux:button icon="arrow-up-tray" wire:click="openImport">Import</flux:button>
                <flux:button variant="primary" icon="plus" wire:click="create">Peserta baru</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Nama, NIS, atau email…" />
        <flux:select wire:model.live="classroom">
            <flux:select.option value="">Semua kelas</flux:select.option>
            @foreach ($classrooms as $c)
                <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="status">
            <flux:select.option value="">Semua status</flux:select.option>
            <flux:select.option value="no_class">Belum punya kelas</flux:select.option>
            <flux:select.option value="no_face">Wajah belum didaftarkan</flux:select.option>
            <flux:select.option value="first_login">Belum ganti kata sandi awal</flux:select.option>
            <flux:select.option value="inactive">Nonaktif</flux:select.option>
        </flux:select>
    </div>

    @if ($participants->isEmpty())
        <x-empty-state icon="user-group" title="Belum ada peserta" description="Tambahkan peserta satu per satu atau import dari Excel/CSV." />
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama</th><th>No. HP</th><th>Asal</th><th>Kelas</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($participants as $participant)
                        <tr wire:key="pt-{{ $participant->id }}">
                            <td>
                                <a href="{{ route('master.participants.show', $participant) }}" wire:navigate class="font-semibold hover:text-woka">{{ $participant->name }}</a>
                                @if ($participant->username)
                                    <span class="block text-xs text-hutan/60 dark:text-santan/60">{{ $participant->username }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ Phone::display($participant->phone) }}</td>
                            <td class="max-w-48 truncate">{{ $participant->profile?->school_name ?? '–' }}</td>
                            <td>{{ $participant->classrooms->first()?->name ?? '–' }}</td>
                            <td class="space-x-1 whitespace-nowrap">
                                @unless ($participant->is_active)
                                    <flux:badge size="sm" color="zinc">Nonaktif</flux:badge>
                                @endunless
                                @if ($participant->must_change_password)
                                    <flux:badge size="sm" color="amber">Sandi awal</flux:badge>
                                @endif
                                @if ($participant->profile?->face_enrolled_at)
                                    <flux:badge size="sm" color="green" icon="face-smile">Wajah</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">Wajah belum</flux:badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-end">
                                @if ($canManage)
                                    <flux:dropdown align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" aria-label="Aksi" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="edit({{ $participant->id }})">Ubah</flux:menu.item>
                                            <flux:menu.item icon="key" wire:click="resetPassword({{ $participant->id }})" wire:confirm="Kembalikan kata sandi {{ $participant->name }} ke sandi awal?">Reset kata sandi</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $participant->id }})" wire:confirm="Hapus peserta {{ $participant->name }}? Data riwayat tetap tersimpan.">Hapus</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $participants->links() }}</div>
    @endif

    @if ($canManage)
    {{-- Form peserta --}}
    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah peserta' : 'Peserta baru' }}</flux:heading>
            @unless ($editingId)
                <flux:callout icon="key" color="amber" text="Akun baru memakai kata sandi awal dan wajib diganti saat login pertama." />
            @endunless

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama lengkap" class="sm:col-span-2" required />
                <flux:input wire:model="form.phone" label="No. HP (untuk login)" type="tel" placeholder="08xxxxxxxxxx" />
                <flux:input wire:model="form.username" label="NIS (opsional)" />
                <flux:input wire:model="form.email" label="Email (opsional)" type="email" />
                <flux:select wire:model="form.classroom_id" label="Kelas">
                    <flux:select.option value="">– Belum ada kelas –</flux:select.option>
                    @foreach ($classrooms as $c)
                        <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.school_name" label="Asal sekolah/kampus" />
                <flux:input wire:model="form.major" label="Jurusan" />
                <flux:select wire:model="form.gender" label="Jenis kelamin">
                    <flux:select.option value="">–</flux:select.option>
                    @foreach ($genders as $gender)
                        <flux:select.option :value="$gender->value">{{ $gender->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.birth_date" label="Tanggal lahir" type="date" />
                <flux:input wire:model="form.emergency_contact_name" label="Kontak darurat" />
                <flux:input wire:model="form.emergency_contact_phone" label="HP kontak darurat" type="tel" />
                <flux:input wire:model="form.target_exam" label="Target ujian" placeholder="IELTS, HSK 3, JFT" />
                <flux:input wire:model="form.target_score" label="Target skor" />
                <flux:textarea wire:model="form.allergies" label="Alergi / pantangan makanan" rows="2" class="sm:col-span-2" />
                <flux:textarea wire:model="form.medical_history" label="Riwayat penyakit (rahasia)" rows="2" class="sm:col-span-2" />
            </div>
            <flux:switch wire:model="form.is_active" label="Akun aktif" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Import --}}
    <flux:modal wire:model.self="showImport" class="w-full max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">Import peserta</flux:heading>
            <flux:text>
                Unggah Excel/CSV dengan kolom minimal <strong>nama</strong> dan <strong>no_hp</strong>. Kolom lain opsional:
                nis, email, asal_sekolah, jurusan, jenis_kelamin (L/P), tanggal_lahir, kelas, alergi, riwayat_penyakit, kontak_darurat_nama, kontak_darurat_hp.
                Peserta yang sudah ada (No. HP/NIS/email sama) akan diperbarui.
            </flux:text>
            <flux:button size="sm" icon="arrow-down-tray" :href="route('master.participants.template')">Unduh template</flux:button>

            <form wire:submit="import" class="space-y-4">
                <flux:input type="file" wire:model="importFile" label="Berkas Excel/CSV" accept=".xlsx,.xls,.csv" />
                <flux:select wire:model="importClassroom" label="Masukkan ke kelas (opsional)">
                    <flux:select.option value="">– Sesuai kolom "kelas" / tanpa kelas –</flux:select.option>
                    @foreach ($classrooms as $c)
                        <flux:select.option :value="$c->id">{{ $c->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray">Import</flux:button>
                </div>
            </form>

            @if ($importResult)
                <flux:callout icon="check-circle" color="green" :heading="$importResult['summary']" />
                @if ($importResult['warnings'])
                    <flux:callout icon="exclamation-triangle" color="amber" heading="Perlu diperiksa">
                        <flux:callout.text>
                            <ul class="list-disc ps-4">
                                @foreach ($importResult['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
                @if ($importResult['notes'])
                    <flux:callout icon="information-circle" heading="Catatan dari berkas">
                        <flux:callout.text>
                            <ul class="list-disc ps-4">
                                @foreach ($importResult['notes'] as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </div>
    </flux:modal>
    @endif
</div>
