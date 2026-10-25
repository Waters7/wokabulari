<?php

use App\Actions\Participants\SaveGuardianContact;
use App\Enums\GuardianRelationship;
use App\Http\Requests\GuardianContactRequest;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Kontak orang tua/wali')] class extends Component {
    public string $guardian_name = '';

    public string $guardian_relationship = 'mother';

    public string $guardian_phone = '';

    public function mount(): void
    {
        $user = auth()->user();

        // Hanya peserta yang wajib mengisi; peran lain langsung ke beranda.
        if (! $user->isParticipant()) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $profile = $user->profile;
        $this->guardian_name = (string) $profile?->guardian_name;
        $this->guardian_relationship = $profile?->guardian_relationship?->value ?? 'mother';
        $this->guardian_phone = $profile?->guardian_phone ? Phone::display($profile->guardian_phone) : '';
    }

    public function save(SaveGuardianContact $save): void
    {
        $data = ['guardian_name' => $this->guardian_name, 'guardian_relationship' => $this->guardian_relationship, 'guardian_phone' => $this->guardian_phone];
        $validator = Validator::make($data, GuardianContactRequest::rulesFor(), GuardianContactRequest::messagesFor(), GuardianContactRequest::attributesFor());

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->messages());
        }

        try {
            $save->handle(auth()->user(), $validator->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['guardian_phone' => $e->getMessage()]);
        }

        session()->flash('status', 'Makasih! Kontak orang tua/wali so tersimpan.');
        $this->redirectRoute('dashboard');
    }

    public function with(): array
    {
        return ['relationships' => GuardianRelationship::cases()];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="text-center">
        <span class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-woka-50 text-woka dark:bg-hutan-800 dark:text-woka-light">
            <flux:icon.user-group class="size-8" />
        </span>
        <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">Kontak orang tua/wali</h1>
        <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">
            Wajib diisi sekali. Dipakai panitia untuk menghubungi keluarga bila ada keperluan penting (izin, sakit, darurat).
        </p>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
        <flux:input wire:model="guardian_name" label="Nama orang tua/wali" autocomplete="off" required />

        <flux:radio.group wire:model="guardian_relationship" label="Hubungan" variant="segmented">
            @foreach ($relationships as $relationship)
                <flux:radio :value="$relationship->value" :label="$relationship->label()" class="data-checked:bg-woka! data-checked:text-white!" />
            @endforeach
        </flux:radio.group>

        <flux:input wire:model="guardian_phone" label="No. HP / WhatsApp orang tua/wali" type="tel" inputmode="tel"
                    placeholder="081234567890" required description="Pastikan nomor aktif dan bisa dihubungi." />

        <flux:button variant="primary" type="submit" class="w-full">Simpan &amp; lanjut</flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-hutan/70 underline-offset-4 hover:underline dark:text-santan/70">Keluar</button>
    </form>
</div>
