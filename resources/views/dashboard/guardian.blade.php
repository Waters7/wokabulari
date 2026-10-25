{{-- Beranda wali: kartu tiap anak terverifikasi dengan status hari ini. --}}
@php
    $wards = auth()->user()->wards()->with('currentRoomAssignment.room.dormitory')->orderBy('name')->get();
    $classToday = \App\Models\DailyAttendance::query()->whereIn('user_id', $wards->pluck('id'))->whereDate('date', today())->get()->keyBy('user_id');
    $dormToday = \App\Models\DormAttendance::query()->whereIn('user_id', $wards->pluck('id'))->whereDate('date', today())->get()->keyBy('user_id');
    $pendingApprovals = \App\Models\DormExitPermit::query()->whereIn('user_id', $wards->pluck('id'))->where('guardian_required', true)->where('status', \App\Enums\ExitPermitStatus::Submitted)->count();
@endphp

<div class="space-y-4">
    @if ($pendingApprovals)
        <flux:callout icon="bell-alert" color="amber" :heading="$pendingApprovals.' izin keluar menunggu persetujuan Bapak/Ibu'">
            <x-slot:actions>
                <flux:button size="sm" :href="route('guardian.approvals')" wire:navigate>Lihat</flux:button>
            </x-slot:actions>
        </flux:callout>
    @endif

    @forelse ($wards as $ward)
        <x-card :href="route('guardian.child', ['anak' => $ward->id])" wire:key="w-{{ $ward->id }}">
            <div class="flex items-center gap-3">
                <flux:avatar :name="$ward->name" :initials="$ward->initials()" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-display text-lg font-bold">{{ $ward->name }}</p>
                    <p class="truncate text-sm text-hutan/60 dark:text-santan/60">{{ $ward->currentRoomAssignment?->room?->dormitory?->name ?? 'Program SMK Go Global' }}</p>
                </div>
                <flux:icon.chevron-right class="size-5 shrink-0 text-hutan/40 dark:text-santan/40" />
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="flex items-center gap-1">Kelas: @if ($c = $classToday->get($ward->id)) <x-status-badge :status="$c->status" /> @else <flux:badge size="sm">Belum absen</flux:badge> @endif</span>
                <span class="flex items-center gap-1">Asrama: @if ($d = $dormToday->get($ward->id)) <x-status-badge :status="$d->status" /> @else <flux:badge size="sm">Belum absen</flux:badge> @endif</span>
            </div>
        </x-card>
    @empty
        <x-empty-state icon="link" title="Belum ada anak terhubung" description="Masukkan kode undangan dari panitia atau dari profil anak.">
            <flux:button :href="route('guardian.link')" wire:navigate variant="primary">Hubungkan anak</flux:button>
        </x-empty-state>
    @endforelse
</div>
