<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth')] #[Title('Persetujuan data pribadi')] class extends Component {
    public bool $agree = false;

    public function mount(): void
    {
        if (! Auth::user()->needsConsent()) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function accept(): void
    {
        $this->validate(['agree' => ['accepted']], ['agree.accepted' => 'Centang persetujuan untuk melanjutkan.']);

        $user = Auth::user();
        $user->forceFill([
            'consented_at' => now(),
            'consent_version' => config('wokabulari.consent_version'),
        ])->save();

        activity('persetujuan')->causedBy($user)->performedOn($user)
            ->withProperties(['versi' => config('wokabulari.consent_version'), 'ip' => request()->ip()])
            ->log('Menyetujui pemrosesan data pribadi');

        $this->redirectRoute('dashboard');
    }
}; ?>

<div class="flex flex-col gap-5">
    <div class="text-center">
        <h1 class="font-display text-2xl font-extrabold text-hutan dark:text-santan">Persetujuan Data Pribadi</h1>
        <p class="mt-1 text-sm text-hutan/70 dark:text-santan/70">Versi {{ config('wokabulari.consent_version') }} · sesuai UU No. 27 Tahun 2022</p>
    </div>

    <div class="max-h-[50vh] space-y-3 overflow-y-auto rounded-xl bg-santan p-4 text-sm leading-relaxed text-hutan/90 dark:text-santan/90 ring-1 ring-woka-100 dark:bg-hutan-950 dark:text-santan/90 dark:ring-hutan-800">
        <p>Wokabulari dikelola oleh <strong>Poltekkes Kemenkes Manado</strong> untuk program <strong>Kelas Bahasa SMK Go Global</strong>. Untuk menjalankan program, kami memproses data berikut:</p>
        <ul class="list-disc space-y-1.5 ps-5">
            <li><strong>Data wajah</strong>: foto selfie dan ciri wajah (descriptor) untuk memastikan absen dilakukan oleh peserta sendiri. Ciri wajah disimpan terenkripsi dan tidak pernah dikirim ke perangkat lain.</li>
            <li><strong>Lokasi GPS</strong>: hanya saat absen kelas/asrama, untuk memastikan peserta berada di lokasi yang ditentukan.</li>
            <li><strong>Data kesehatan</strong>: riwayat penyakit, alergi, dan laporan sakit di asrama, untuk penanganan dan penyesuaian makanan. Detail hanya dapat dilihat petugas asrama/kesehatan, super admin, dan wali peserta.</li>
            <li><strong>Data belajar</strong>: kehadiran, nilai, tugas, quiz, dan catatan pengajar.</li>
        </ul>
        <p>Selfie absen dihapus otomatis paling lambat 90 hari setelah angkatan selesai. Data lain disimpan sesuai kebijakan instansi.</p>
        <p>Ngana berhak meminta salinan, perbaikan, atau penghapusan data dengan menghubungi admin program. Persetujuan dapat ditarik, namun beberapa fitur (absen wajah, laporan kesehatan) tidak dapat digunakan tanpa data tersebut.</p>
    </div>

    <form wire:submit="accept" class="flex flex-col gap-4">
        <flux:checkbox wire:model="agree" label="Saya sudah membaca dan menyetujui pemrosesan data pribadi di atas." />
        @error('agree') <flux:text class="text-rica!">{{ $message }}</flux:text> @enderror

        <flux:button type="submit" variant="primary" class="w-full">Setuju dan lanjutkan</flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-hutan/70 dark:text-santan/70 underline-offset-4 hover:underline dark:text-santan/70">Keluar</button>
    </form>
</div>
