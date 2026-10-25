<?php

use App\Actions\Participants\SaveGuardianContact;
use App\Actions\Users\UpdateLoginEmail;
use App\Enums\GuardianRelationship;
use App\Http\Requests\GuardianContactRequest;
use App\Http\Requests\LoginEmailRequest;
use App\Services\ImageService;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profil')] class extends Component {
    use WithFileUploads;

    public string $name = '';

    public ?string $email = '';

    public ?string $phone = '';

    public bool $leaderboard_anonymous = false;

    public $photo = null;

    public string $guardian_name = '';

    public string $guardian_relationship = 'mother';

    public string $guardian_phone = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ? Phone::display($user->phone) : '';
        $this->leaderboard_anonymous = $user->leaderboard_anonymous;

        $profile = $user->profile;
        $this->guardian_name = (string) $profile?->guardian_name;
        $this->guardian_relationship = $profile?->guardian_relationship?->value ?? 'mother';
        $this->guardian_phone = $profile?->guardian_phone ? Phone::display($profile->guardian_phone) : '';
    }

    public function save(ImageService $images, SaveGuardianContact $guardianContact, UpdateLoginEmail $loginEmail): void
    {
        $user = Auth::user();
        $phone = Phone::normalize($this->phone);
        $canEditEmail = $user->can('updateLoginEmail', $user);

        if ($canEditEmail) {
            $this->email = LoginEmailRequest::normalize($this->email);
        }

        $validated = $this->validate([
            'name' => $user->isParticipant() ? ['nullable'] : ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'leaderboard_anonymous' => ['boolean'],
            ...($canEditEmail ? LoginEmailRequest::rulesFor($user) : []),
        ], LoginEmailRequest::messagesFor());

        if ($phone && \App\Models\User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            $this->addError('phone', 'Nomor HP sudah dipakai akun lain.');

            return;
        }

        $email = $canEditEmail ? ($validated['email'] ?? null) : $user->email;

        if (! $phone && blank($email) && blank($user->username)) {
            $this->addError('phone', 'Isi No. HP atau email supaya ngana tetap bisa login.');

            return;
        }

        // Nama peserta dikelola admin program agar sesuai data resmi.
        if (! $user->isParticipant()) {
            $user->name = $validated['name'];
        }

        $user->phone = $phone;
        $user->leaderboard_anonymous = $validated['leaderboard_anonymous'];

        // Kontak orang tua/wali wajib untuk peserta.
        if ($user->isParticipant()) {
            $contact = ['guardian_name' => $this->guardian_name, 'guardian_relationship' => $this->guardian_relationship, 'guardian_phone' => $this->guardian_phone];
            $validator = Validator::make($contact, GuardianContactRequest::rulesFor(), GuardianContactRequest::messagesFor(), GuardianContactRequest::attributesFor());
            if ($validator->fails()) {
                throw \Illuminate\Validation\ValidationException::withMessages($validator->errors()->messages());
            }
            try {
                $guardianContact->handle($user, $validator->validated());
            } catch (\InvalidArgumentException $e) {
                $this->addError('guardian_phone', $e->getMessage());

                return;
            }
        }

        if ($this->photo) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }
            // Foto dari kamera HP diperkecil (maks. 600 px) agar ringan.
            $user->photo_path = $images->storeUpload($this->photo, 'avatars', 600, 82, 'public');
            $this->photo = null;
        }

        $user->save();

        if ($canEditEmail) {
            $loginEmail->handle($user, $email);
            $this->email = $user->email;
        }

        Flux::toast(variant: 'success', text: 'Profil tersimpan. Mantap!');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Profil" subheading="Perbarui kontak dan foto profil ngana.">
        <div class="mt-4 flex flex-wrap gap-2">
            <flux:button size="sm" icon="key" :href="route('security.edit')" wire:navigate>Ganti kata sandi</flux:button>
        </div>
        <form wire:submit="save" class="my-6 w-full space-y-5">
            <div class="flex items-center gap-4">
                <flux:avatar size="xl" :name="auth()->user()->name" :initials="auth()->user()->initials()"
                             :src="$photo ? $photo->temporaryUrl() : auth()->user()->photo_url" />
                <div>
                    <flux:input type="file" wire:model="photo" accept="image/*" label="Foto profil" />
                    <flux:error name="photo" />
                </div>
            </div>

            @if (auth()->user()->isParticipant())
                <flux:input label="Nama" :value="$name" disabled description="Nama dikelola admin program sesuai data resmi." />
            @else
                <flux:input wire:model="name" label="Nama" required autocomplete="name" />
            @endif

            @if (auth()->user()->username)
                <flux:input label="NIS/NIP" :value="auth()->user()->username" disabled />
            @endif

            <flux:input wire:model="phone" label="Nomor HP (WhatsApp)" type="tel" inputmode="tel" autocomplete="tel"
                        description="Dipakai untuk login dan notifikasi WhatsApp." />

            @can('updateLoginEmail', auth()->user())
                <flux:input wire:model="email" label="Email" type="email" autocomplete="email" placeholder="nama@contoh.com"
                            description="Opsional. Setelah diisi, ngana bisa login pakai email atau No. HP." />
            @else
                <flux:input label="Email" :value="$email ?: '–'" disabled description="Email akun admin dikelola lewat server." />
            @endcan

            @if (auth()->user()->isParticipant())
                <fieldset class="space-y-4 rounded-2xl border border-woka-100 p-4 dark:border-hutan-800">
                    <legend class="px-1 text-sm font-semibold">Orang tua/wali (wajib)</legend>
                    <flux:input wire:model="guardian_name" label="Nama orang tua/wali" required />
                    <flux:radio.group wire:model="guardian_relationship" label="Hubungan" variant="segmented">
                        @foreach (GuardianRelationship::cases() as $relationship)
                            <flux:radio :value="$relationship->value" :label="$relationship->label()" class="data-checked:bg-woka! data-checked:text-white!" />
                        @endforeach
                    </flux:radio.group>
                    <flux:input wire:model="guardian_phone" label="No. HP / WhatsApp orang tua/wali" type="tel" inputmode="tel" required />
                </fieldset>

                <flux:switch wire:model="leaderboard_anonymous" label="Sembunyikan nama di peringkat"
                             description="Nama tampil sebagai inisial di leaderboard." />
            @endif

            <flux:button variant="primary" type="submit">Simpan</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
