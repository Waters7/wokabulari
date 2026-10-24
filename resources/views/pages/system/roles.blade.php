<?php

use App\Enums\Role;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

new #[Title('Role & hak akses')] class extends Component {
    /** @var array<string, list<string>> */
    public array $matrix = [];

    public function mount(): void
    {
        foreach (RoleModel::with('permissions')->get() as $role) {
            $this->matrix[$role->name] = $role->permissions->pluck('name')->all();
        }
    }

    public function save(): void
    {
        $this->authorize('role.manage');

        foreach ($this->matrix as $roleName => $permissions) {
            // Super admin selalu semua akses; pimpinan selalu read-only.
            if ($roleName === Role::SuperAdmin->value) {
                continue;
            }

            if ($roleName === Role::Leader->value) {
                $permissions = array_values(array_filter($permissions, fn ($p) => ! str_ends_with($p, '.manage') && ! in_array($p, ['attendance.approve', 'leave.approve', 'health.handle', 'meal.proxy', 'dorm-exit.approve', 'dorm-request.handle', 'guardian.verify', 'attendance.confirm-dorm'], true)));
            }

            RoleModel::findByName($roleName, 'web')->syncPermissions($permissions);
        }

        activity('pengaturan')->causedBy(auth()->user())->log('Mengubah hak akses role');
        Flux::toast(variant: 'success', text: 'Hak akses tersimpan.');
    }

    public function with(): array
    {
        return [
            'roles' => collect(Role::cases())->reject(fn (Role $r) => $r === Role::SuperAdmin),
            'permissions' => Permission::query()->orderBy('name')->pluck('name')->groupBy(fn ($p) => explode('.', $p)[0]),
        ];
    }
}; ?>

<div>
    <x-page-header title="Role & hak akses" subtitle="Atur permission tiap role tanpa mengubah kode. Super admin selalu memiliki semua akses; pimpinan selalu read-only.">
        <x-slot:actions>
            <flux:button variant="primary" icon="check" wire:click="save">Simpan</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:callout icon="information-circle" class="mb-4" text="Permission tim kerja diberikan otomatis sesuai unit (konsumsi, asrama, kesehatan) saat akun disimpan." />

    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Permission</th>
                    @foreach ($roles as $role)
                        <th class="text-center">{{ $role->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $group => $items)
                    @foreach ($items as $permission)
                        <tr wire:key="perm-{{ $permission }}">
                            <td class="font-mono text-xs">{{ $permission }}</td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    <input type="checkbox" class="size-5 rounded accent-woka" value="{{ $permission }}" wire:model="matrix.{{ $role->value }}"
                                           aria-label="{{ $role->label() }}: {{ $permission }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>
