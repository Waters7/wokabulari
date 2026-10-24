<?php

use App\Actions\Attendance\EnrollFace;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Wajah referensi')] class extends Component {
    public bool $enrolled = false;

    public ?string $error = null;

    public function mount(): void
    {
        $this->enrolled = (bool) auth()->user()->profile?->face_enrolled_at;
    }

    /**
     * @param  array{descriptors?: list<list<float>>, selfie?: string|null}  $payload
     */
    public function enroll(array $payload, EnrollFace $enroll): void
    {
        $this->error = null;

        try {
            $enroll->handle(auth()->user(), $payload['descriptors'] ?? [], $payload['selfie'] ?? null);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->enrolled = true;
        Flux::toast(variant: 'success', text: 'Mantap! Wajah referensi so terdaftar.');
    }
}; ?>

<div>
    <x-page-header title="Wajah referensi" subtitle="Dipakai untuk memastikan absen dilakukan oleh ngana sendiri." :back="route('profile.edit')" />

    <div class="mx-auto max-w-md space-y-4">
        @if ($enrolled)
            <flux:callout icon="check-circle" color="green" heading="Wajah sudah terdaftar"
                          text="Ngana sudah bisa absen harian dan absen asrama. Hubungi admin bila perlu mendaftar ulang." />
            @if (Route::has('attendance.daily'))
                <flux:button variant="primary" class="w-full" icon="camera" :href="route('attendance.daily')" wire:navigate>Ke absen harian</flux:button>
            @endif
        @else
            <x-card>
                <ol class="list-decimal space-y-1 ps-5 text-sm">
                    <li>Cari tempat yang terang, lepas masker/kacamata hitam.</li>
                    <li>Pastikan hanya wajah ngana yang terlihat di kamera.</li>
                    <li>Ikuti perintah di layar (kedip atau menoleh), lalu lihat lurus.</li>
                    <li>Aplikasi mengambil 3 sampel wajah secara otomatis.</li>
                </ol>
            </x-card>

            @if ($error)
                <flux:callout icon="exclamation-triangle" color="red" :text="$error" />
            @endif

            <x-face-camera mode="enroll" action="enroll" :samples="3" start-label="Mulai daftarkan wajah" wire:key="enroll-{{ $error ? md5($error) : 'cam' }}" />
        @endif
    </div>
</div>
