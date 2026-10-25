<?php

use App\Actions\Guardians\ManageGuardians;
use App\Enums\GuardianRelationship;
use App\Http\Requests\GuardianRegistrationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Daftar wali')] class extends Component {
    /** @var array<string, mixed> */
    public array $form = ['code' => '', 'name' => '', 'phone' => '', 'email' => '', 'relationship' => 'mother', 'password' => '', 'password_confirmation' => ''];

    public ?string $childName = null;

    public function mount(?string $code = null): void
    {
        $this->form['code'] = strtoupper((string) $code);
        $this->checkCode(app(ManageGuardians::class));
    }

    public function updatedFormCode(ManageGuardians $guardians): void
    {
        $this->form['code'] = strtoupper(trim($this->form['code']));
        $this->checkCode($guardians);
    }

    protected function checkCode(ManageGuardians $guardians): void
    {
        $this->childName = null;
        if (strlen($this->form['code']) >= 8) {
            try {
                $invitation = $guardians->findUsable($this->form['code']);
                // Tampilkan nama depan saja agar kode yang bocor tidak membuka identitas lengkap.
                $this->childName = strtok($invitation->participant->name, ' ');
            } catch (\InvalidArgumentException) {
                $this->childName = null;
            }
        }
    }

    public function register(ManageGuardians $guardians): void
    {
        $key = 'guardian-register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['form.code' => 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.']);
        }
        RateLimiter::hit($key, 300);

        $validator = Validator::make($this->form, GuardianRegistrationRequest::rulesFor(), [
            'phone.regex' => 'Nomor HP tidak valid (contoh 081234567890).',
        ], GuardianRegistrationRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        try {
            $guardian = $guardians->register($this->form['code'], $validator->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['form.code' => $e->getMessage()]);
        }

        Auth::login($guardian);
        session()->regenerate();
        $this->redirectRoute('dashboard');
    }

    public function with(): array
    {
        return ['relationships' => GuardianRelationship::cases()];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">Daftar akun wali</h1>
        <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">Pantau kehadiran, nilai, dan kabar anak di program SMK Go Global.</p>
    </div>

    <form wire:submit="register" class="flex flex-col gap-4">
        <flux:input wire:model.live.debounce.400ms="form.code" label="Kode undangan" placeholder="8 huruf/angka" autocomplete="off"
                    class:input="text-center font-mono text-xl tracking-[0.3em] uppercase" maxlength="12" />
        @if ($childName)
            <flux:callout icon="check-circle" color="green" :text="'Kode valid untuk peserta: '.$childName.'…'" />
        @endif
        <flux:input wire:model="form.name" label="Nama lengkap" autocomplete="name" />
        <flux:input wire:model="form.phone" label="Nomor HP (WhatsApp)" inputmode="tel" placeholder="081234567890" autocomplete="tel"
                    description="Dipakai untuk masuk dan menerima kabar anak." />
        <flux:input wire:model="form.email" type="email" label="Email (opsional)" autocomplete="email" />
        <flux:radio.group wire:model="form.relationship" label="Hubungan dengan peserta" variant="segmented">
            @foreach ($relationships as $relationship)
                <flux:radio :value="$relationship->value" :label="$relationship->label()" />
            @endforeach
        </flux:radio.group>
        <flux:input wire:model="form.password" type="password" label="Kata sandi" autocomplete="new-password" viewable description="Minimal 8 karakter." />
        <flux:input wire:model="form.password_confirmation" type="password" label="Ulangi kata sandi" autocomplete="new-password" viewable />
        <flux:button type="submit" variant="primary" class="w-full">Daftar</flux:button>
    </form>

    <p class="text-center text-sm text-hutan/70 dark:text-santan/70">Sudah punya akun? <a href="{{ route('login') }}" wire:navigate class="font-semibold text-woka underline">Masuk</a></p>
</div>
