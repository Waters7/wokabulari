<?php

use App\Actions\Guardians\ManageGuardians;
use App\Enums\GuardianRelationship;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hubungkan anak')] class extends Component {
    public string $code = '';

    public string $relationship = 'mother';

    public function mount(): void
    {
        abort_unless(auth()->user()->isGuardian(), 403);
    }

    public function link(ManageGuardians $guardians): void
    {
        $this->resetErrorBag();
        $key = 'guardian-link:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'Terlalu banyak percobaan. Coba lagi nanti.');

            return;
        }
        RateLimiter::hit($key, 300);

        try {
            $guardians->link(auth()->user(), $this->code, $this->relationship);
        } catch (\InvalidArgumentException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->code = '';
        Flux::toast(variant: 'success', text: 'Permintaan terkirim. Tunggu verifikasi panitia, neh.');
    }

    public function with(): array
    {
        return [
            'links' => DB::table('guardian_participant')->join('users', 'users.id', '=', 'guardian_participant.participant_id')
                ->where('guardian_id', auth()->id())->orderBy('users.name')
                ->get(['users.name', 'guardian_participant.relationship', 'guardian_participant.verified_at']),
            'relationships' => GuardianRelationship::cases(),
        ];
    }
}; ?>

<div class="mx-auto max-w-md">
    <x-page-header title="Hubungkan anak" subtitle="Masukkan kode dari panitia atau dari profil anak." />

    <x-card class="mb-5">
        <form wire:submit="link" class="space-y-4">
            <flux:input wire:model="code" label="Kode undangan" maxlength="12" autocomplete="off" class:input="text-center font-mono text-xl tracking-[0.3em] uppercase" />
            <flux:radio.group wire:model="relationship" label="Hubungan" variant="segmented">
                @foreach ($relationships as $r)
                    <flux:radio :value="$r->value" :label="$r->label()" />
                @endforeach
            </flux:radio.group>
            <flux:button type="submit" variant="primary" class="w-full" icon="link">Hubungkan</flux:button>
        </form>
    </x-card>

    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-hutan/60 dark:text-santan/60">Anak terhubung</h2>
    <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
        @forelse ($links as $link)
            <div class="flex items-center justify-between gap-2 px-4 py-3 text-sm">
                <div>
                    <p class="font-semibold">{{ $link->name }}</p>
                    <p class="text-xs text-hutan/60 dark:text-santan/60">{{ \App\Enums\GuardianRelationship::tryFrom($link->relationship)?->label() }}</p>
                </div>
                @if ($link->verified_at)
                    <flux:badge color="green" size="sm">Terverifikasi</flux:badge>
                @else
                    <flux:badge color="amber" size="sm">Menunggu verifikasi</flux:badge>
                @endif
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-hutan/60 dark:text-santan/60">Belum ada anak terhubung.</p>
        @endforelse
    </x-card>
</div>
