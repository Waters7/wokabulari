<?php

use App\Actions\Vendors\ManageVendorAccounts;
use App\Http\Requests\VendorAccountRequest;
use App\Http\Requests\VendorRequest;
use App\Livewire\Concerns\WithCrudModal;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vendor makanan')] class extends Component {
    use WithCrudModal;

    public bool $showAccounts = false;

    public ?int $accountsVendorId = null;

    /** @var array<string, string> */
    public array $account = ['name' => '', 'phone' => '', 'email' => '', 'password' => ''];

    public ?int $resettingId = null;

    public string $newPassword = '';

    public function openAccounts(int $vendorId): void
    {
        abort_unless(auth()->user()->can('vendor.manage'), 403);
        $vendor = Vendor::query()->findOrFail($vendorId);
        $this->resetErrorBag();
        $this->accountsVendorId = $vendor->id;
        $this->account = ['name' => (string) $vendor->contact_person, 'phone' => $vendor->phone ? Phone::display($vendor->phone) : '', 'email' => '', 'password' => ''];
        $this->reset('resettingId', 'newPassword');
        $this->showAccounts = true;
    }

    public function createAccount(ManageVendorAccounts $accounts): void
    {
        abort_unless(auth()->user()->can('vendor.manage'), 403);
        $this->resetErrorBag();
        $validator = Validator::make($this->account, VendorAccountRequest::rulesFor(), [], VendorAccountRequest::attributesFor());

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["account.{$k}" => $m])->all());
        }

        try {
            $user = $accounts->create(Vendor::query()->findOrFail($this->accountsVendorId), $validator->validated(), auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('account.phone', $e->getMessage());

            return;
        }

        $this->account = ['name' => '', 'phone' => '', 'email' => '', 'password' => ''];
        Flux::toast(variant: 'success', text: "Akun {$user->name} dibuat. Login pakai No. HP; wajib ganti kata sandi saat login pertama.");
    }

    public function resetAccountPassword(ManageVendorAccounts $accounts): void
    {
        abort_unless(auth()->user()->can('vendor.manage'), 403);
        $this->resetErrorBag();
        $this->validate(['newPassword' => ['required', 'string', 'min:8']], [], ['newPassword' => 'kata sandi baru']);
        $account = User::query()->where('vendor_id', $this->accountsVendorId)->findOrFail($this->resettingId);
        $accounts->resetPassword($account, $this->newPassword, auth()->user());
        $this->reset('resettingId', 'newPassword');
        Flux::toast(variant: 'success', text: "Kata sandi {$account->name} diatur ulang.");
    }

    public function toggleAccount(int $userId, ManageVendorAccounts $accounts): void
    {
        abort_unless(auth()->user()->can('vendor.manage'), 403);
        $account = User::query()->where('vendor_id', $this->accountsVendorId)->findOrFail($userId);
        $active = $accounts->toggleActive($account, auth()->user());
        Flux::toast(text: "Akun {$account->name} ".($active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    protected function modelClass(): string
    {
        return Vendor::class;
    }

    protected function defaults(): array
    {
        return ['name' => '', 'contact_person' => '', 'phone' => '', 'address' => '', 'is_active' => true];
    }

    protected function formRules(): array
    {
        return VendorRequest::rulesFor();
    }

    protected function labels(): array
    {
        return ['name' => 'nama vendor', 'contact_person' => 'narahubung'];
    }

    protected function persist(array $data, ?Model $model): void
    {
        $data['phone'] = Phone::normalize($data['phone'] ?? null);
        ($model ?? new Vendor)->fill($data)->save();
    }

    public function with(): array
    {
        return [
            'vendorAccounts' => $this->showAccounts && $this->accountsVendorId
                ? User::query()->where('vendor_id', $this->accountsVendorId)->orderBy('name')->get()
                : collect(),
            'accountsVendor' => $this->accountsVendorId ? Vendor::query()->find($this->accountsVendorId) : null,
            'vendors' => Vendor::query()->withCount('accounts')
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderByDesc('is_active')->orderBy('name')->paginate(20),
        ];
    }
}; ?>

<div>
    <x-page-header title="Vendor makanan" subtitle="Penyedia konsumsi peserta (data master unit konsumsi).">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="create">Vendor baru</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($vendors as $vendor)
            <x-card wire:key="v-{{ $vendor->id }}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-display text-lg font-bold">{{ $vendor->name }}</p>
                        <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $vendor->contact_person ?? '–' }} · {{ Phone::display($vendor->phone) }}</p>
                        <p class="mt-1 line-clamp-2 text-sm">{{ $vendor->address }}</p>
                    </div>
                    <div class="flex shrink-0">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $vendor->id }})" aria-label="Ubah" />
                        <x-confirm-button action="delete({{ $vendor->id }})" title="Hapus vendor {{ $vendor->name }}?" />
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2">
                    <div>
                        @unless ($vendor->is_active)
                            <flux:badge size="sm" color="zinc">Nonaktif</flux:badge>
                        @endunless
                    </div>
                    <flux:button size="sm" icon="key" wire:click="openAccounts({{ $vendor->id }})">Akun login ({{ $vendor->accounts_count }})</flux:button>
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="building-storefront" title="Belum ada vendor" />
        @endforelse
    </div>
    <div class="mt-4">{{ $vendors->links() }}</div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Ubah vendor' : 'Vendor baru' }}</flux:heading>
            <flux:input wire:model="form.name" label="Nama vendor" required />
            <flux:input wire:model="form.contact_person" label="Narahubung" />
            <flux:input wire:model="form.phone" label="No. HP" type="tel" />
            <flux:textarea wire:model="form.address" label="Alamat" rows="2" />
            <flux:switch wire:model="form.is_active" label="Vendor aktif" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Akun login vendor --}}
    <flux:modal wire:model.self="showAccounts" class="w-full max-w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Akun login · {{ $accountsVendor?->name }}</flux:heading>
                <flux:text class="mt-1">Vendor login dengan No. HP lalu melapor pengantaran: jumlah, menu, jam siap, tempat, dan foto.</flux:text>
            </div>

            @if ($vendorAccounts->isNotEmpty())
                <div class="divide-y divide-zinc-100 rounded-xl ring-1 ring-zinc-200/70 dark:divide-hutan-800 dark:ring-hutan-800">
                    @foreach ($vendorAccounts as $vendorAccount)
                        <div class="px-3 py-2.5 text-sm" wire:key="va-{{ $vendorAccount->id }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $vendorAccount->name }}</p>
                                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ Phone::display($vendorAccount->phone) }}@unless ($vendorAccount->is_active) · <span class="text-red-600">nonaktif</span>@endunless</p>
                                </div>
                                <div class="flex shrink-0">
                                    <flux:button size="xs" variant="ghost" icon="key" wire:click="$set('resettingId', {{ $vendorAccount->id }})" aria-label="Atur ulang kata sandi" />
                                    <flux:button size="xs" variant="ghost" :icon="$vendorAccount->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleAccount({{ $vendorAccount->id }})" :aria-label="$vendorAccount->is_active ? 'Nonaktifkan' : 'Aktifkan'" />
                                </div>
                            </div>
                            @if ($resettingId === $vendorAccount->id)
                                <form wire:submit="resetAccountPassword" class="mt-2 flex items-start gap-2">
                                    <flux:input wire:model="newPassword" type="password" size="sm" placeholder="Kata sandi baru (min. 8)" viewable class="min-w-0 flex-1" />
                                    <flux:button size="sm" type="submit" variant="primary">Simpan</flux:button>
                                </form>
                                <flux:error name="newPassword" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="createAccount" class="space-y-3">
                <p class="text-sm font-semibold">Tambah akun</p>
                <flux:input wire:model="account.name" label="Nama" required />
                <flux:input wire:model="account.phone" label="No. HP (untuk login)" type="tel" placeholder="081234567890" required />
                <flux:input wire:model="account.email" label="Email (opsional)" type="email" />
                <flux:input wire:model="account.password" label="Kata sandi awal" type="password" viewable required description="Wajib diganti vendor saat login pertama." />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="plus">Buat akun</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
