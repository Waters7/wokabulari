<?php

use App\Models\User;
use App\Support\Phone;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profil peserta')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        abort_unless($user->isParticipant(), 404);
        $this->authorize('view', $user);
        $this->user = $user->load(['profile', 'classrooms.language', 'classrooms.batch', 'currentRoomAssignment.room.dormitory']);
    }

    public function resetFace(): void
    {
        $this->authorize('manageFace', $this->user);

        $profile = $this->user->profile;
        if ($profile?->face_reference_path) {
            Storage::disk('private')->delete($profile->face_reference_path);
        }
        $profile?->update(['face_descriptor' => null, 'face_reference_path' => null, 'face_enrolled_at' => null]);

        activity('peserta')->causedBy(auth()->user())->performedOn($this->user)->log('Mengatur ulang wajah referensi');
        Flux::toast(text: 'Wajah referensi dihapus. Peserta perlu mendaftarkan ulang.');
    }

    public function with(): array
    {
        return [
            'canSeeHealth' => auth()->user()->can('health.view-detail') || auth()->user()->can('participant.manage'),
            'canManage' => auth()->user()->can('update', $this->user),
            'sections' => collect(['partials.participant-360'])->filter(fn ($view) => view()->exists($view)),
        ];
    }
}; ?>

<div>
    <x-page-header :title="$user->name" :back="route('master.participants')" :subtitle="$user->profile?->school_name" />

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Data diri">
            <div class="mb-4 flex items-center gap-3">
                <flux:avatar size="lg" :name="$user->name" :initials="$user->initials()" :src="$user->photo_url" />
                <div>
                    <p class="font-semibold">{{ $user->name }}</p>
                    <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $user->classrooms->first()?->label() ?? 'Belum punya kelas' }}</p>
                </div>
            </div>
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'No. HP' => Phone::display($user->phone),
                    'NIS' => $user->username ?? '–',
                    'Email' => $user->email ?? '–',
                    'Jenis kelamin' => $user->profile?->gender?->label() ?? '–',
                    'Tempat, tanggal lahir' => collect([$user->profile?->birth_place, $user->profile?->birth_date ? tanggal($user->profile->birth_date) : null])->filter()->join(', ') ?: '–',
                    'Alamat' => $user->profile?->address ?: '–',
                    'Kelas · jurusan' => collect([$user->profile?->school_grade, $user->profile?->major])->filter()->join(' · ') ?: '–',
                    'Ukuran baju' => $user->profile?->shirt_size ?? '–',
                    'Biodata' => ($user->profile?->biodataCompletion() ?? 0).'% lengkap',
                    'Asrama' => $user->profile?->lives_in_dorm === false
                        ? 'Tidak tinggal di asrama'
                        : ($user->currentRoomAssignment ? $user->currentRoomAssignment->room->dormitory->name.' · Kamar '.$user->currentRoomAssignment->room->number : 'Belum dapat kamar'),
                    'Kontak darurat' => trim(($user->profile?->emergency_contact_name ?? '–').' '.($user->profile?->emergency_contact_phone ? '('.Phone::display($user->profile->emergency_contact_phone).')' : '')),
                    'Orang tua/wali' => $user->profile?->hasGuardianContact()
                        ? trim(($user->profile->guardian_name ?? '–').($user->profile->guardian_relationship ? ' · '.$user->profile->guardian_relationship->label() : '').' ('.Phone::display($user->profile->guardian_phone).')')
                        : 'Belum diisi',
                    'Target ujian' => trim(($user->profile?->target_exam ?? '–').' '.($user->profile?->target_score ?? '')),
                    'Login terakhir' => $user->last_login_at ? tanggal_jam($user->last_login_at) : 'Belum pernah',
                ] as $label => $value)
                    <div class="flex justify-between gap-3"><dt class="text-hutan/60 dark:text-santan/60">{{ $label }}</dt><dd class="text-end font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-card>

        <div class="space-y-4">
            <x-card title="Wajah referensi">
                @if ($user->profile?->face_enrolled_at)
                    <flux:callout icon="face-smile" color="green" heading="Sudah terdaftar" :text="'Didaftarkan '.tanggal_jam($user->profile->face_enrolled_at)" />
                    @if ($canManage)
                        <div class="mt-3">
                            <x-confirm-button action="resetFace" label="Daftarkan ulang" icon="arrow-path" variant="filled" title="Hapus wajah referensi?" message="Peserta harus mendaftarkan wajah lagi sebelum bisa absen." confirmLabel="Hapus" />
                        </div>
                    @endif
                @else
                    <flux:callout icon="exclamation-triangle" color="amber" heading="Belum terdaftar" text="Peserta mendaftarkan wajah sendiri dari menu Profil → Wajah referensi saat login." />
                @endif
            </x-card>

            <x-card title="Kesehatan">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-hutan/60 dark:text-santan/60">Alergi / pantangan</dt><dd class="font-semibold">{{ $user->profile?->allergies ?: 'Tidak ada' }}</dd></div>
                    @if ($canSeeHealth)
                        <div><dt class="text-hutan/60 dark:text-santan/60">Golongan darah</dt><dd class="font-semibold">{{ $user->profile?->blood_type ?: '–' }}</dd></div>
                        <div><dt class="text-hutan/60 dark:text-santan/60">Riwayat penyakit</dt><dd class="font-semibold">{{ $user->profile?->medical_history ?: 'Tidak ada' }}</dd></div>
                    @else
                        <flux:text class="text-xs">Riwayat penyakit hanya untuk petugas kesehatan/asrama.</flux:text>
                    @endif
                </dl>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Akun">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-hutan/60 dark:text-santan/60">Status</dt><dd>{!! $user->is_active ? '<span class="font-semibold text-woka">Aktif</span>' : '<span class="font-semibold text-rica">Nonaktif</span>' !!}</dd></div>
                    <div class="flex justify-between"><dt class="text-hutan/60 dark:text-santan/60">Kata sandi</dt><dd class="font-semibold">{{ $user->must_change_password ? 'Masih sandi awal' : 'Sudah diganti' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-hutan/60 dark:text-santan/60">Persetujuan data</dt><dd class="font-semibold">{{ $user->consented_at ? tanggal($user->consented_at) : 'Belum' }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>

    @foreach ($sections as $section)
        @include($section, ['user' => $user])
    @endforeach
</div>
