<?php

use App\Actions\Guardians\ManageGuardians;
use App\Models\GuardianInvitation;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Wali saya')] class extends Component {
    public ?int $invitationId = null;

    public function generate(ManageGuardians $guardians): void
    {
        $this->authorize('share', GuardianInvitation::class);
        $this->invitationId = $guardians->shareCode(auth()->user())->id;
    }

    public function with(): array
    {
        $user = auth()->user();

        return [
            'invitation' => $this->invitationId ? GuardianInvitation::query()->where('participant_id', $user->id)->find($this->invitationId) : null,
            'guardians' => $user->allGuardians()->orderBy('name')->get(),
            'profile' => $user->profile,
        ];
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Wali saya" subtitle="Bagikan kode supaya orang tua/wali bisa memantau perkembanganmu." />

    <x-card class="mb-5 text-center" x-data="{ copied: false }">
        @if ($invitation)
            <p class="text-sm text-hutan/70 dark:text-santan/70">Kode untuk wali (berlaku s.d. {{ tanggal($invitation->expires_at) }})</p>
            <p class="my-3 font-mono text-3xl font-bold tracking-[0.3em]">{{ $invitation->code }}</p>
            <flux:button icon="clipboard-document" class="w-full" x-on:click="navigator.clipboard.writeText(@js('Daftar portal wali Wokabulari: '.$invitation->url().' (kode '.$invitation->code.')')); copied = true">
                <span x-show="!copied">Salin tautan untuk wali</span><span x-show="copied" x-cloak>Tersalin</span>
            </flux:button>
            @if ($profile?->hasGuardianContact())
                <flux:button icon="chat-bubble-left-right" variant="primary" class="mt-2 w-full" target="_blank"
                             :href="'https://wa.me/'.$profile->guardian_phone.'?text='.rawurlencode('Daftar portal wali Wokabulari: '.$invitation->url().' (kode '.$invitation->code.')')">
                    Kirim ke WhatsApp {{ $profile->guardian_name }}
                </flux:button>
            @endif
            <p class="mt-3 text-xs text-hutan/60 dark:text-santan/60">Kirim lewat WhatsApp ke orang tua/wali. Hubungan diverifikasi panitia sebelum wali bisa melihat datamu.</p>
        @else
            <flux:icon.user-group class="mx-auto size-12 text-woka" />
            <p class="mt-2 text-sm">Wali bisa melihat kehadiran, nilai, izin, dan laporan sakitmu — tidak termasuk selfie dan rating anonim.</p>
            <flux:button variant="primary" class="mt-4 w-full" icon="key" wire:click="generate">Buat kode untuk wali</flux:button>
        @endif
    </x-card>

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Wali terhubung</h2>
    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
        @forelse ($guardians as $guardian)
            <div class="flex items-center justify-between gap-2 px-4 py-3 text-sm" wire:key="g-{{ $guardian->id }}">
                <div>
                    <p class="font-semibold">{{ $guardian->name }}</p>
                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ \App\Enums\GuardianRelationship::tryFrom($guardian->pivot->relationship)?->label() }}</p>
                </div>
                @if ($guardian->pivot->verified_at)
                    <flux:badge color="green" size="sm">Terverifikasi</flux:badge>
                @else
                    <flux:badge color="amber" size="sm">Menunggu verifikasi</flux:badge>
                @endif
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-hutan/60 dark:text-santan/60">Belum ada wali terhubung.</p>
        @endforelse
    </x-card>
</div>
