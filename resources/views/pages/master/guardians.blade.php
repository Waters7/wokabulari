<?php

use App\Actions\Guardians\ManageGuardians;
use App\Enums\GuardianRelationship;
use App\Enums\Role;
use App\Http\Requests\GuardianRegistrationRequest;
use App\Models\GuardianInvitation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Wali peserta')] class extends Component {
    #[Url]
    public string $tab = 'pending';

    public string $search = '';

    public bool $showInvite = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $lastInvitationId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', GuardianInvitation::class);
    }

    public function verify(int $guardianId, int $participantId, ManageGuardians $guardians): void
    {
        $this->authorize('verify', GuardianInvitation::class);

        try {
            $guardians->verify(auth()->user(), User::query()->findOrFail($guardianId), User::query()->findOrFail($participantId));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Hubungan wali diverifikasi.');
    }

    public function reject(int $guardianId, int $participantId, ManageGuardians $guardians): void
    {
        $this->authorize('verify', GuardianInvitation::class);
        $guardians->reject(auth()->user(), User::query()->findOrFail($guardianId), User::query()->findOrFail($participantId));
        Flux::toast(text: 'Penautan ditolak.');
    }

    public function invite(): void
    {
        $this->authorize('create', GuardianInvitation::class);
        $this->resetErrorBag();
        $this->form = ['participant_id' => null, 'phone' => '', 'email' => '', 'relationship' => GuardianRelationship::Mother->value];
        $this->lastInvitationId = null;
        $this->showInvite = true;
    }

    public function sendInvite(ManageGuardians $guardians): void
    {
        $this->authorize('create', GuardianInvitation::class);

        $validator = Validator::make($this->form, GuardianRegistrationRequest::invitationRules(), ['phone.regex' => 'Nomor HP tidak valid.'], GuardianRegistrationRequest::attributesFor());
        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ['form.'.$k => $m])->all());
        }

        $data = $validator->validated();
        try {
            $invitation = $guardians->invite(User::query()->findOrFail($data['participant_id']), auth()->user(), $data['phone'] ?? null, $data['email'] ?? null, $data['relationship'] ?? null);
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.participant_id', $e->getMessage());

            return;
        }

        $this->lastInvitationId = $invitation->id;
        Flux::toast(variant: 'success', text: 'Undangan dibuat'.($invitation->phone || $invitation->email ? ' dan dikirim.' : '.'));
    }

    public function revoke(int $id): void
    {
        $this->authorize('create', GuardianInvitation::class);
        GuardianInvitation::query()->whereNull('used_at')->whereKey($id)->update(['expires_at' => now()]);
        Flux::toast(text: 'Undangan dibatalkan.');
    }

    public function with(): array
    {
        $links = DB::table('guardian_participant as gp')
            ->join('users as g', 'g.id', '=', 'gp.guardian_id')
            ->join('users as p', 'p.id', '=', 'gp.participant_id')
            ->when($this->tab === 'pending', fn ($q) => $q->whereNull('gp.verified_at'), fn ($q) => $q->whereNotNull('gp.verified_at'))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('g.name', 'like', "%{$this->search}%")->orWhere('p.name', 'like', "%{$this->search}%")))
            ->orderByDesc('gp.created_at')->limit(100)
            ->get(['gp.guardian_id', 'gp.participant_id', 'gp.relationship', 'gp.verified_at', 'gp.created_at', 'g.name as guardian_name', 'g.phone as guardian_phone', 'p.name as participant_name']);

        return [
            'links' => $this->tab === 'invitations' ? collect() : $links,
            'pendingCount' => DB::table('guardian_participant')->whereNull('verified_at')->count(),
            'invitations' => $this->tab === 'invitations'
                ? GuardianInvitation::query()->with(['participant', 'usedBy', 'creator'])->latest()->limit(50)->get()
                : collect(),
            'participants' => $this->showInvite ? User::query()->role(Role::Participant->value)->active()->orderBy('name')->get(['id', 'name']) : collect(),
            'relationships' => GuardianRelationship::cases(),
            'lastInvitation' => $this->lastInvitationId ? GuardianInvitation::query()->find($this->lastInvitationId) : null,
        ];
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <x-page-header title="Wali peserta" subtitle="Verifikasi hubungan wali–peserta dan kirim undangan portal wali.">
        <x-slot:actions>
            <flux:button variant="primary" icon="user-plus" wire:click="invite">Undang wali</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="mb-4">
        <flux:radio value="pending" :label="'Menunggu ('.$pendingCount.')'" />
        <flux:radio value="verified" label="Terverifikasi" />
        <flux:radio value="invitations" label="Undangan" />
    </flux:radio.group>

    @if ($tab !== 'invitations')
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama wali atau peserta" class="mb-3" />
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @forelse ($links as $link)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" wire:key="l-{{ $link->guardian_id }}-{{ $link->participant_id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $link->guardian_name }} <span class="text-sm font-normal text-hutan/60 dark:text-santan/60">({{ GuardianRelationship::tryFrom($link->relationship)?->label() }})</span></p>
                        <p class="text-sm">Wali dari <strong>{{ $link->participant_name }}</strong></p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ \App\Support\Phone::display($link->guardian_phone) }} · {{ tanggal_jam($link->created_at) }}</p>
                    </div>
                    @if (! $link->verified_at)
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="primary" icon="check" wire:click="verify({{ $link->guardian_id }}, {{ $link->participant_id }})">Verifikasi</flux:button>
                            <x-confirm-button action="reject({{ $link->guardian_id }}, {{ $link->participant_id }})" label="Tolak" icon="x-mark" title="Tolak penautan ini?" message="Wali tidak akan bisa melihat data peserta ini." confirmLabel="Ya, tolak" />
                        </div>
                    @else
                        <flux:badge color="green" size="sm">Terverifikasi</flux:badge>
                    @endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Tidak ada data.</p>
            @endforelse
        </x-card>
    @else
        <x-card :padding="false" class="divide-y divide-woka-100 dark:divide-hutan-800">
            @forelse ($invitations as $inv)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm" wire:key="inv-{{ $inv->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $inv->participant?->name }} · <span class="font-mono">{{ $inv->code }}</span></p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">
                            {{ $inv->isFromAdmin() ? 'Dari '.$inv->creator?->name : 'Dibagikan peserta' }}
                            @if ($inv->phone) · {{ \App\Support\Phone::display($inv->phone) }} @endif
                            · berlaku s.d. {{ tanggal($inv->expires_at) }}
                        </p>
                    </div>
                    @if ($inv->used_at)
                        <flux:badge size="sm" color="green">Dipakai {{ $inv->usedBy?->name }}</flux:badge>
                    @elseif ($inv->isUsable())
                        <x-confirm-button action="revoke({{ $inv->id }})" label="Batalkan" icon="x-mark" title="Batalkan undangan?" message="Kode tidak bisa dipakai lagi." confirmLabel="Ya, batalkan" />
                    @else
                        <flux:badge size="sm">Kedaluwarsa</flux:badge>
                    @endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-hutan/60 dark:text-santan/60">Belum ada undangan.</p>
            @endforelse
        </x-card>
    @endif

    <flux:modal wire:model.self="showInvite" class="w-full max-w-md">
        @if ($lastInvitation)
            <div class="space-y-4 text-center" x-data="{ copied: false }">
                <flux:heading size="lg">Undangan siap</flux:heading>
                <p class="font-mono text-3xl font-bold tracking-[0.3em]">{{ $lastInvitation->code }}</p>
                <p class="break-all text-sm text-hutan/70 dark:text-santan/70">{{ $lastInvitation->url() }}</p>
                <flux:button icon="clipboard-document" class="w-full" x-on:click="navigator.clipboard.writeText(@js('Undangan portal wali Wokabulari: '.$lastInvitation->url().' (kode '.$lastInvitation->code.')')); copied = true">
                    <span x-show="!copied">Salin tautan untuk dibagikan</span><span x-show="copied" x-cloak>Tersalin</span>
                </flux:button>
                <flux:modal.close><flux:button variant="ghost" class="w-full">Selesai</flux:button></flux:modal.close>
            </div>
        @else
            <form wire:submit="sendInvite" class="space-y-4">
                <flux:heading size="lg">Undang wali</flux:heading>
                <flux:select wire:model="form.participant_id" label="Peserta" placeholder="Pilih peserta">
                    @foreach ($participants as $p)
                        <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.phone" label="Nomor HP wali (WhatsApp)" inputmode="tel" placeholder="081234567890"
                            description="Bila nomor saat mendaftar sama, hubungan langsung terverifikasi." />
                <flux:input wire:model="form.email" type="email" label="Email wali (opsional)" />
                <flux:radio.group wire:model="form.relationship" label="Hubungan" variant="segmented">
                    @foreach ($relationships as $r)
                        <flux:radio :value="$r->value" :label="$r->label()" />
                    @endforeach
                </flux:radio.group>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Buat undangan</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
