<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Ganti kata sandi')] class extends Component {
    use PasswordValidationRules;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! Auth::user()->must_change_password) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function save(): void
    {
        $user = Auth::user();

        $this->validate([
            'password' => [
                ...$this->passwordRules(),
                // Tidak boleh sama dengan kata sandi lama/awal
                function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                    if (Hash::check($value, $user->password)) {
                        $fail('Kata sandi baru tidak boleh sama dengan kata sandi awal.');
                    }
                },
            ],
        ]);

        $user->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
        ])->save();

        activity('pengguna')->causedBy($user)->performedOn($user)->log('Mengganti kata sandi awal');

        session()->flash('status', 'Mantap! Kata sandi so diganti.');

        $this->redirectRoute('dashboard');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">Ganti kata sandi</h1>
        <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">
            Halo {{ auth()->user()->name }}! Ini login pertama ngana. Demi keamanan, ganti kata sandi awal dengan kata sandi milikmu sendiri.
        </p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
        <flux:input wire:model="password" label="Kata sandi baru" type="password" required autocomplete="new-password" viewable
                    description="Minimal 8 karakter. Jangan pakai tanggal lahir atau nomor HP." />

        <flux:input wire:model="password_confirmation" label="Ulangi kata sandi baru" type="password" required autocomplete="new-password" viewable />

        <flux:button variant="primary" type="submit" class="w-full">Simpan kata sandi</flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-hutan/70 underline-offset-4 hover:underline dark:text-santan/70">Keluar</button>
    </form>
</div>
