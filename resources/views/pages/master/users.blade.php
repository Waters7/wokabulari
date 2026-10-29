<?php

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Http\Requests\StaffUserRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\User;
use App\Services\StaffUnitService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Pengguna & Tim')] class extends Component {
    use WithCrudModal;

    #[Url]
    public string $role = '';

    protected function modelClass(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'email' => '', 'username' => '', 'phone' => '', 'roles' => [Role::Teacher->value], 'units' => [], 'password' => '', 'is_active' => true];
    }

    protected function formRules(): array
    {
        return StaffUserRequest::rulesFor($this->editingId ? User::find($this->editingId) : null, $this->form);
    }

    protected function labels(): array
    {
        return ['name' => 'nama', 'roles' => 'role', 'units' => 'unit tim kerja', 'username' => 'NIP', 'password' => 'kata sandi'];
    }

    protected function findModel(int $id): Model
    {
        return User::query()->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [Role::Participant->value, Role::Guardian->value, Role::Vendor->value]))->findOrFail($id);
    }

    protected function fillFrom(Model $model): array
    {
        return [
            'name' => $model->name,
            'email' => $model->email,
            'username' => $model->username,
            'phone' => $model->phone ? Phone::display($model->phone) : '',
            'roles' => $model->getRoleNames()->all(),
            'units' => $model->staffUnits->map(fn ($u) => $u->unit->value)->all(),
            'password' => '',
            'is_active' => $model->is_active,
        ];
    }

    protected function persist(array $data, ?Model $model): void
    {
        // Super admin hanya boleh diberikan oleh super admin.
        if (in_array(Role::SuperAdmin->value, $data['roles'], true) && ! auth()->user()->isSuperAdmin()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.roles' => 'Hanya super admin yang dapat memberi role Super Admin.']);
        }

        DB::transaction(function () use ($data, $model) {
            $user = $model ?? new User;
            $user->fill([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'username' => $data['username'] ?: null,
                'phone' => Phone::normalize($data['phone'] ?? null),
                'is_active' => (bool) $data['is_active'],
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }

            $user->save();
            $user->syncRoles($data['roles']);

            $units = in_array(Role::Staff->value, $data['roles'], true) ? ($data['units'] ?? []) : [];
            app(StaffUnitService::class)->sync($user, $units);
        });
    }

    public function with(): array
    {
        return [
            'users' => User::query()
                ->with(['roles', 'staffUnits'])
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', [Role::Participant->value, Role::Guardian->value, Role::Vendor->value]))
                ->when($this->role, fn (Builder $q) => $q->role($this->role))
                ->search($this->search)
                ->orderBy('name')
                ->paginate(25),
            'roleOptions' => collect(Role::cases())->reject(fn (Role $r) => in_array($r, [Role::Participant, Role::Guardian, Role::Vendor], true)),
            'units' => StaffUnit::cases(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Pengguna & Tim" subtitle="Akun admin, pimpinan, pengajar, dan tim kerja (konsumsi, asrama, kesehatan).">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Pengguna baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="Nama, NIP, atau email…" />
        <flux:select wire:model.live="role">
            <flux:select.option value="">Semua role</flux:select.option>
            @foreach ($roleOptions as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="table-scroll">
        <table>
            <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Unit</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="u-{{ $user->id }}">
                        <td>
                            <span class="font-semibold">{{ $user->name }}</span>
                            @if ($user->username)<span class="block text-xs text-hutan/60 dark:text-santan/60">NIP {{ $user->username }}</span>@endif
                        </td>
                        <td>{{ $user->email }}</td>
                        <td class="space-x-1">
                            @foreach ($user->roles as $r)
                                <x-status-badge :status="Role::tryFrom($r->name)" />
                            @endforeach
                        </td>
                        <td class="space-x-1">
                            @foreach ($user->staffUnits as $unit)
                                <x-status-badge :status="$unit->unit" />
                            @endforeach
                        </td>
                        <td><flux:badge size="sm" :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge></td>
                        <td class="whitespace-nowrap text-end">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $user->id }})" aria-label="Ubah" />
                            @unless ($user->is(auth()->user()))
                                <x-confirm-button action="delete({{ $user->id }})" title="Hapus akun {{ $user->name }}?" />
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="Belum ada pengguna" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah pengguna' : 'Pengguna baru' }}</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama lengkap" class="sm:col-span-2" required />
                <flux:input wire:model="form.email" label="Email" type="email" required />
                <flux:input wire:model="form.username" label="NIP (opsional)" />
                <flux:input wire:model="form.phone" label="No. HP" type="tel" />
                <flux:input wire:model="form.password" label="{{ $editingId ? 'Kata sandi baru (opsional)' : 'Kata sandi awal' }}" type="password" viewable autocomplete="new-password" />
            </div>

            <flux:checkbox.group wire:model.live="form.roles" label="Role"
                                 description="Boleh lebih dari satu, mis. Tim Kerja + Pengajar untuk anggota tim kerja yang juga mengajar.">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roleOptions as $option)
                        <flux:checkbox :value="$option->value" :label="$option->label()" />
                    @endforeach
                </div>
            </flux:checkbox.group>
            <flux:error name="form.roles" />

            @if (in_array('staff', $form['roles'] ?? [], true))
                <flux:checkbox.group wire:model="form.units" label="Unit tim kerja">
                    @foreach ($units as $unit)
                        <flux:checkbox :value="$unit->value" :label="$unit->label()" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="form.units" />
            @endif

            <flux:switch wire:model="form.is_active" label="Akun aktif" />
            <flux:text class="text-xs">Kata sandi yang diisi admin wajib diganti pengguna saat login berikutnya.</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
