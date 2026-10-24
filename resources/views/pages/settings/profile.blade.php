<?php

use App\Support\Phone;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ? Phone::display($user->phone) : '';
        $this->leaderboard_anonymous = $user->leaderboard_anonymous;
    }

    public function save(): void
    {
        $user = Auth::user();
        $phone = Phone::normalize($this->phone);

        $validated = $this->validate([
            'name' => $user->isParticipant() ? ['nullable'] : ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'leaderboard_anonymous' => ['boolean'],
        ]);

        if ($phone && \App\Models\User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            $this->addError('phone', 'Nomor HP sudah dipakai akun lain.');

            return;
        }

        // Nama peserta dikelola admin program agar sesuai data resmi.
        if (! $user->isParticipant()) {
            $user->name = $validated['name'];
        }

        $user->email = $validated['email'] ? Str::lower($validated['email']) : null;
        $user->phone = $phone;
        $user->leaderboard_anonymous = $validated['leaderboard_anonymous'];

        if ($this->photo) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }
            $user->photo_path = $this->photo->storeAs('avatars', Str::uuid().'.'.$this->photo->extension(), 'public');
            $this->photo = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: 'Profil tersimpan. Mantap!');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="Profil" subheading="Perbarui kontak dan foto profil ngana.">
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

            <flux:input wire:model="email" label="Email" type="email" autocomplete="email" />

            @if (auth()->user()->isParticipant())
                <flux:switch wire:model="leaderboard_anonymous" label="Sembunyikan nama di peringkat"
                             description="Nama tampil sebagai inisial di leaderboard." />
            @endif

            <flux:button variant="primary" type="submit">Simpan</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
