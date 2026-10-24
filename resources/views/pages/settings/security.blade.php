<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kata sandi')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: 'Kata sandi berhasil diubah.');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Ubah kata sandi" subheading="Gunakan kata sandi yang panjang dan sulit ditebak.">
        <form wire:submit="updatePassword" class="mt-6 space-y-5">
            <flux:input wire:model="current_password" label="Kata sandi saat ini" type="password" required autocomplete="current-password" viewable />
            <flux:input wire:model="password" label="Kata sandi baru" type="password" required autocomplete="new-password" viewable />
            <flux:input wire:model="password_confirmation" label="Ulangi kata sandi baru" type="password" required autocomplete="new-password" viewable />

            <flux:button variant="primary" type="submit" data-test="update-password-button">Simpan</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
