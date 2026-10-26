<?php

use App\Actions\Classrooms\AssignParticipants;
use App\Actions\Classrooms\DistributeParticipants;
use App\Actions\Dorm\AssignRooms;
use App\Actions\Dorm\SetDormResidency;
use App\Actions\Participants\ImportParticipants;
use App\Actions\Participants\SaveParticipant;
use App\Actions\Participants\SetParticipantGender;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\BulkClassroomRequest;
use App\Http\Requests\BulkDormRequest;
use App\Http\Requests\ParticipantRequest;
use App\Imports\ParticipantRowsImport;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\Batch;
use App\Models\Classroom;
use App\Models\DormRoom;
use App\Models\User;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
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

    /** @var list<int|string> Peserta terpilih untuk aksi massal */
    public array $selected = [];

    public bool $showBulkClass = false;

    public string $bulkClassMode = 'single';

    public ?int $bulkClassroom = null;

    /** @var list<int|string> */
    public array $bulkClassrooms = [];

    public bool $showBulkDorm = false;

    public string $bulkDormMode = 'auto';

    public ?int $bulkRoom = null;

    public string $bulkGender = '';

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
            'emergency_contact_phone' => '', 'guardian_name' => '', 'guardian_relationship' => '', 'guardian_phone' => '',
            'lives_in_dorm' => true, 'target_exam' => '', 'target_score' => '',
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
        return ['name' => 'nama', 'username' => 'NIS', 'phone' => 'nomor HP', 'classroom_id' => 'kelas', 'school_name' => 'asal sekolah',
            'guardian_name' => 'nama orang tua/wali', 'guardian_phone' => 'HP orang tua/wali', 'guardian_relationship' => 'hubungan'];
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
            'guardian_name' => $profile?->guardian_name,
            'guardian_relationship' => $profile?->guardian_relationship?->value ?? '',
            'guardian_phone' => $profile?->guardian_phone ? Phone::display($profile->guardian_phone) : '',
            'lives_in_dorm' => $profile?->lives_in_dorm ?? true,
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

    protected function filteredQuery(): Builder
    {
        return User::query()
            ->participants()
            ->search($this->search)
            ->when($this->classroom, fn (Builder $q) => $q->whereHas('classrooms', fn ($q) => $q->where('classrooms.id', $this->classroom)))
            ->when($this->status === 'no_class', fn (Builder $q) => $q->whereDoesntHave('classrooms'))
            ->when($this->status === 'no_room', fn (Builder $q) => $q->where('is_active', true)
                ->whereDoesntHave('profile', fn ($p) => $p->where('lives_in_dorm', false))
                ->whereDoesntHave('roomAssignments', fn ($r) => $r->whereNull('check_out_date')))
            ->when($this->status === 'non_resident', fn (Builder $q) => $q->whereHas('profile', fn ($p) => $p->where('lives_in_dorm', false)))
            ->when($this->status === 'no_gender', fn (Builder $q) => $q->whereDoesntHave('profile', fn ($p) => $p->whereNotNull('gender')))
            ->when($this->status === 'no_face', fn (Builder $q) => $q->whereDoesntHave('profile', fn ($q) => $q->whereNotNull('face_enrolled_at')))
            ->when($this->status === 'first_login', fn (Builder $q) => $q->where('must_change_password', true))
            ->when($this->status === 'inactive', fn (Builder $q) => $q->where('is_active', false));
    }

    // ---- Aksi massal (makro) ----

    public function selectAllFiltered(): void
    {
        abort_unless($this->canBulk(), 403);
        $this->selected = $this->filteredQuery()->pluck('users.id')->all();
    }

    public function clearSelection(): void
    {
        $this->reset('selected');
    }

    protected function canBulk(): bool
    {
        return auth()->user()->can('participant.manage') || auth()->user()->can('dormitory.manage');
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    protected function selectedParticipants(): \Illuminate\Support\Collection
    {
        return User::query()->participants()->with(['profile', 'currentRoomAssignment.room'])->whereIn('id', $this->selected)->orderBy('name')->get();
    }

    public function openBulkClass(): void
    {
        abort_unless(auth()->user()->can('participant.manage'), 403);
        $this->resetErrorBag();
        $this->bulkClassMode = 'single';
        $this->bulkClassroom = $this->classroom;
        $this->bulkClassrooms = [];
        $this->showBulkClass = true;
    }

    public function applyBulkClass(AssignParticipants $assign, DistributeParticipants $distribute): void
    {
        abort_unless(auth()->user()->can('participant.manage'), 403);
        $data = $this->validateBulk(BulkClassroomRequest::rulesFor(), BulkClassroomRequest::messagesFor(), [
            'user_ids' => $this->selected, 'mode' => $this->bulkClassMode,
            'classroom_id' => $this->bulkClassroom, 'classroom_ids' => $this->bulkClassrooms,
        ]);

        if ($data['mode'] === 'single') {
            $classroom = Classroom::query()->findOrFail($data['classroom_id']);
            $count = $assign->handle($classroom, $data['user_ids'], auth()->user());
            $message = "{$count} peserta ditempatkan di {$classroom->label()}.";
        } else {
            $plan = $distribute->plan($this->selectedParticipants(), Classroom::query()->whereIn('id', $data['classroom_ids'])->get());
            $count = $distribute->apply($plan, auth()->user());
            $message = "{$count} peserta dibagi rata ke ".count($data['classroom_ids']).' kelas.';
        }

        $this->showBulkClass = false;
        $this->reset('selected');
        Flux::toast(variant: 'success', text: $message);
    }

    public function openBulkDorm(): void
    {
        abort_unless(auth()->user()->can('dormitory.manage'), 403);
        $this->resetErrorBag();
        $this->bulkDormMode = 'auto';
        $this->bulkRoom = null;
        $this->bulkGender = '';
        $this->showBulkDorm = true;
    }

    public function applyBulkDorm(AssignRooms $rooms, SetDormResidency $residency, SetParticipantGender $setGender): void
    {
        abort_unless(auth()->user()->can('dormitory.manage'), 403);
        $data = $this->validateBulk(BulkDormRequest::rulesFor(), BulkDormRequest::messagesFor(), [
            'user_ids' => $this->selected, 'mode' => $this->bulkDormMode, 'room_id' => $this->bulkRoom, 'gender' => $this->bulkGender ?: null,
        ]);
        $actor = auth()->user();

        // Jenis kelamin adalah data profil peserta: butuh izin kelola peserta juga.
        abort_if($data['mode'] === 'gender' && ! $actor->can('participant.manage'), 403);

        $message = match ($data['mode']) {
            'auto' => (function () use ($rooms, $actor) {
                $plan = $rooms->plan($this->selectedParticipants());
                $placed = $rooms->apply($plan['placements'], $actor);

                return "{$placed} peserta ditempatkan di kamar".(count($plan['skipped']) ? ', '.count($plan['skipped']).' dilewati.' : '.');
            })(),
            'room' => (function () use ($rooms, $actor, $data) {
                $room = DormRoom::query()->with('dormitory')->findOrFail($data['room_id']);
                $placed = 0;
                $failed = [];
                foreach ($this->selectedParticipants() as $participant) {
                    try {
                        $rooms->assign($room, $participant, $actor);
                        $placed++;
                    } catch (InvalidArgumentException $e) {
                        $failed[] = $e->getMessage();
                    }
                }

                return "{$placed} peserta dipindahkan ke kamar {$room->number}.".($failed ? ' Gagal: '.implode(' ', array_unique($failed)) : '');
            })(),
            'resident' => $residency->handle($data['user_ids'], true, $actor).' peserta ditandai tinggal di asrama.',
            'non_resident' => $residency->handle($data['user_ids'], false, $actor).' peserta ditandai tidak tinggal di asrama.',
            'gender' => $setGender->handle($data['user_ids'], Gender::from($data['gender']), $actor).' peserta diisi jenis kelaminnya ('.Gender::from($data['gender'])->label().').',
        };

        $this->showBulkDorm = false;
        $this->reset('selected');
        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function validateBulk(array $rules, array $messages, array $input): array
    {
        $this->resetErrorBag();
        $validator = Validator::make($input, $rules, $messages);

        if ($validator->fails()) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($validator->errors()->messages())
                ->mapWithKeys(fn ($m, $k) => [str_starts_with($k, 'user_ids') ? 'selected' : $k => $m])->all());
        }

        return $validator->validated();
    }

    /** Pratinjau aksi massal yang sedang dibuka. @return array<string, mixed> */
    protected function bulkPreview(\Illuminate\Support\Collection $classrooms): array
    {
        if ($this->showBulkClass && $this->bulkClassMode === 'distribute' && $this->bulkClassrooms !== []) {
            $participants = $this->selectedParticipants();
            $targets = $classrooms->whereIn('id', array_map('intval', $this->bulkClassrooms))->values();
            $distribute = app(DistributeParticipants::class);

            return ['classSummary' => $distribute->summary($distribute->plan($participants, $targets), $participants)];
        }

        if ($this->showBulkDorm && $this->bulkDormMode === 'auto') {
            $participants = $this->selectedParticipants();
            $plan = app(AssignRooms::class)->plan($participants);
            $rooms = DormRoom::query()->with('dormitory')->whereIn('id', array_unique($plan['placements']))->get()->keyBy('id');

            return ['dormPlan' => [
                'rooms' => collect($plan['placements'])->groupBy(fn ($roomId) => $roomId, preserveKeys: true)
                    ->map(fn ($users, $roomId) => [
                        'label' => $rooms[$roomId]->dormitory->name.' · Kamar '.$rooms[$roomId]->number,
                        'names' => $participants->whereIn('id', $users->keys())->pluck('name')->all(),
                    ])->values()->all(),
                'skipped' => collect($plan['skipped'])->map(fn ($reason, $userId) => [
                    'name' => $participants->firstWhere('id', $userId)?->name, 'reason' => $reason,
                ])->values()->all(),
            ]];
        }

        return [];
    }

    public function with(): array
    {
        $batch = Batch::current();
        $classrooms = Classroom::query()->with('language')->withCount('participants')->when($batch, fn ($q) => $q->where('batch_id', $batch->id))->orderBy('name')->get();

        return [
            'participants' => $this->filteredQuery()
                ->with(['profile', 'currentRoomAssignment.room', 'classrooms' => fn ($q) => $q->when($batch, fn ($q) => $q->where('batch_id', $batch->id))])
                ->orderBy('name')
                ->paginate(25),
            'classrooms' => $classrooms,
            'genders' => Gender::cases(),
            'relationships' => GuardianRelationship::cases(),
            'canManage' => auth()->user()->can('participant.manage'),
            'canDorm' => auth()->user()->can('dormitory.manage'),
            'canBulk' => $this->canBulk(),
            'dormRooms' => $this->showBulkDorm && $this->bulkDormMode === 'room'
                ? DormRoom::query()->with('dormitory')->withCount('activeAssignments')->get()
                    ->sortBy(fn ($room) => [$room->dormitory?->name, strlen($room->number), $room->number])->values()
                : collect(),
            'classSummary' => [],
            'dormPlan' => null,
            ...$this->bulkPreview($classrooms),
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
            <flux:select.option value="no_room">Asrama: belum dapat kamar</flux:select.option>
            <flux:select.option value="non_resident">Tidak tinggal di asrama</flux:select.option>
            <flux:select.option value="no_gender">Jenis kelamin belum diisi</flux:select.option>
            <flux:select.option value="no_face">Wajah belum didaftarkan</flux:select.option>
            <flux:select.option value="first_login">Belum ganti kata sandi awal</flux:select.option>
            <flux:select.option value="inactive">Nonaktif</flux:select.option>
        </flux:select>
    </div>

    @if ($participants->isEmpty())
        <x-empty-state icon="user-group" title="Belum ada peserta" description="Tambahkan peserta satu per satu atau import dari Excel/CSV." />
    @else
        @if ($canBulk)
            <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                <flux:button size="sm" icon="check-circle" wire:click="selectAllFiltered">Pilih semua hasil ({{ $participants->total() }})</flux:button>
                <span class="text-hutan/60 dark:text-santan/60">lalu atur kelas atau asrama sekaligus.</span>
            </div>
        @endif
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @if ($canBulk)<th class="w-8"><span class="sr-only">Pilih</span></th>@endif
                        <th>Nama</th><th>No. HP</th><th>Asal</th><th>Kelas</th><th>Asrama</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($participants as $participant)
                        <tr wire:key="pt-{{ $participant->id }}">
                            @if ($canBulk)
                                <td>
                                    <input type="checkbox" wire:model.live="selected" value="{{ $participant->id }}" aria-label="Pilih {{ $participant->name }}"
                                           class="size-5 rounded border-woka-200 accent-woka">
                                </td>
                            @endif
                            <td>
                                <a href="{{ route('master.participants.show', $participant) }}" wire:navigate class="font-semibold hover:text-woka">{{ $participant->name }}</a>
                                @if ($participant->username)
                                    <span class="block text-xs text-hutan/60 dark:text-santan/60">{{ $participant->username }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ Phone::display($participant->phone) }}</td>
                            <td class="max-w-48 truncate">{{ $participant->profile?->school_name ?? '–' }}</td>
                            <td>{{ $participant->classrooms->first()?->name ?? '–' }}</td>
                            <td class="whitespace-nowrap">
                                @if ($participant->profile?->lives_in_dorm === false)
                                    <flux:badge size="sm" color="zinc">Tidak asrama</flux:badge>
                                @elseif ($participant->currentRoomAssignment?->room)
                                    Kamar {{ $participant->currentRoomAssignment->room->number }}
                                @else
                                    <span class="text-xs text-amber-700 dark:text-amber-400">Belum dapat kamar</span>
                                @endif
                            </td>
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

    {{-- Bilah aksi massal --}}
    @if ($canBulk && count($selected) > 0)
        {{-- Di atas bottom nav bila peran memakainya; selain itu menempel di bawah layar. --}}
        <div @class([
                'fixed inset-x-3 z-30 mx-auto flex max-w-xl items-center gap-2 rounded-2xl bg-hutan p-3 text-santan shadow-lg lg:bottom-6',
                'bottom-20' => auth()->user()->primaryRole()?->usesBottomNav(),
                'bottom-4' => ! auth()->user()->primaryRole()?->usesBottomNav(),
            ]) data-test="bulk-bar">
            <span class="me-auto text-sm font-semibold">{{ count($selected) }} <span class="hidden sm:inline">peserta</span> dipilih</span>
            @if ($canManage)
                <flux:button size="sm" icon="academic-cap" wire:click="openBulkClass"><span class="sm:hidden">Kelas</span><span class="hidden sm:inline">Atur kelas</span></flux:button>
            @endif
            @if ($canDorm)
                <flux:button size="sm" icon="home-modern" wire:click="openBulkDorm"><span class="sm:hidden">Asrama</span><span class="hidden sm:inline">Atur asrama</span></flux:button>
            @endif
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearSelection" aria-label="Batal pilih" class="text-santan!" />
        </div>
        <div class="h-24 lg:h-16"></div>
    @endif

    @if ($canManage)
        <flux:modal wire:model.self="showBulkClass" class="w-full max-w-lg">
            <form wire:submit="applyBulkClass" class="space-y-4">
                <div>
                    <flux:heading size="lg">Atur kelas {{ count($selected) }} peserta</flux:heading>
                    <flux:text class="mt-1">Peserta yang sudah di kelas lain pada angkatan ini akan dipindahkan.</flux:text>
                </div>
                <flux:error name="selected" />

                <flux:radio.group wire:model.live="bulkClassMode" variant="segmented">
                    <flux:radio value="single" label="Satu kelas" class="data-checked:bg-woka! data-checked:text-white!" />
                    <flux:radio value="distribute" label="Bagi rata" class="data-checked:bg-woka! data-checked:text-white!" />
                </flux:radio.group>

                @if ($bulkClassMode === 'single')
                    <flux:select wire:model="bulkClassroom" label="Kelas tujuan">
                        <flux:select.option value="">– Pilih kelas –</flux:select.option>
                        @foreach ($classrooms as $c)
                            <flux:select.option :value="$c->id">{{ $c->label() }} ({{ $c->participants_count }} peserta)</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="classroom_id" />
                @else
                    <flux:checkbox.group wire:model.live="bulkClassrooms" label="Bagi ke kelas" description="Kelas dengan peserta paling sedikit diisi lebih dulu; jenis kelamin diseimbangkan.">
                        @foreach ($classrooms as $c)
                            <flux:checkbox :value="$c->id" :label="$c->label().' · '.$c->participants_count.' peserta'" />
                        @endforeach
                    </flux:checkbox.group>
                    <flux:error name="classroom_ids" />

                    @if ($classSummary)
                        <div class="rounded-xl bg-santan p-3 text-sm dark:bg-hutan-800" data-test="class-preview">
                            <p class="mb-2 font-semibold">Pratinjau</p>
                            <ul class="space-y-1">
                                @foreach ($classrooms->whereIn('id', array_keys($classSummary)) as $c)
                                    @php($row = $classSummary[$c->id])
                                    <li class="flex justify-between gap-2">
                                        <span class="min-w-0 truncate">{{ $c->label() }}</span>
                                        <span class="shrink-0 font-semibold">+{{ $row['added'] }} <span class="font-normal text-hutan/60 dark:text-santan/60">(L {{ $row['male'] }} · P {{ $row['female'] }})</span></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($canDorm)
        <flux:modal wire:model.self="showBulkDorm" class="w-full max-w-lg">
            <form wire:submit="applyBulkDorm" class="space-y-4">
                <flux:heading size="lg">Atur asrama {{ count($selected) }} peserta</flux:heading>
                <flux:error name="selected" />

                <flux:radio.group wire:model.live="bulkDormMode" label="Tindakan">
                    <flux:radio value="auto" label="Isi kamar otomatis" description="Ditempatkan di kamar kosong sesuai jenis kelamin dan sisa kapasitas." />
                    <flux:radio value="room" label="Pindahkan ke kamar tertentu" />
                    <flux:radio value="non_resident" label="Tandai tidak tinggal di asrama" description="Dikeluarkan dari kamar; tidak ikut absen malam, izin keluar, dan keluhan asrama." />
                    <flux:radio value="resident" label="Tandai tinggal di asrama" />
                    @if ($canManage)
                        <flux:radio value="gender" label="Isi jenis kelamin" description="Wajib sebelum isi kamar otomatis (asrama putra/putri)." />
                    @endif
                </flux:radio.group>

                @if ($bulkDormMode === 'gender')
                    <flux:radio.group wire:model="bulkGender" variant="segmented">
                        @foreach ($genders as $gender)
                            <flux:radio :value="$gender->value" :label="$gender->label()" class="data-checked:bg-woka! data-checked:text-white!" />
                        @endforeach
                    </flux:radio.group>
                    <flux:error name="gender" />
                @endif

                @if ($bulkDormMode === 'room')
                    <flux:select wire:model="bulkRoom" label="Kamar tujuan">
                        <flux:select.option value="">– Pilih kamar –</flux:select.option>
                        @foreach ($dormRooms as $room)
                            <flux:select.option :value="$room->id">{{ $room->dormitory?->name }} · Kamar {{ $room->number }} ({{ $room->active_assignments_count }}/{{ $room->capacity }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="room_id" />
                @endif

                @if ($bulkDormMode === 'auto' && $dormPlan)
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-xl bg-santan p-3 text-sm dark:bg-hutan-800" data-test="dorm-preview">
                        <p class="font-semibold">Pratinjau: {{ collect($dormPlan['rooms'])->sum(fn ($r) => count($r['names'])) }} ditempatkan, {{ count($dormPlan['skipped']) }} dilewati</p>
                        @foreach ($dormPlan['rooms'] as $row)
                            <p><span class="font-semibold">{{ $row['label'] }}:</span> {{ implode(', ', $row['names']) }}</p>
                        @endforeach
                        @foreach ($dormPlan['skipped'] as $row)
                            <p class="text-amber-800 dark:text-amber-300">{{ $row['name'] }} — {{ $row['reason'] }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </form>
        </flux:modal>
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
                <flux:input wire:model="form.guardian_name" label="Nama orang tua/wali" />
                <flux:select wire:model="form.guardian_relationship" label="Hubungan dengan peserta">
                    <flux:select.option value="">–</flux:select.option>
                    @foreach ($relationships as $relationship)
                        <flux:select.option :value="$relationship->value">{{ $relationship->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.guardian_phone" label="HP orang tua/wali" type="tel" placeholder="081234567890"
                            description="Boleh kosong; peserta wajib mengisinya saat login." />
                <flux:switch wire:model="form.lives_in_dorm" label="Tinggal di asrama" class="sm:col-span-2"
                             description="Matikan untuk peserta pulang-pergi: dikeluarkan dari kamar dan konsumsinya mengikuti pengaturan tim konsumsi." />
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
