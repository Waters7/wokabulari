<?php

use App\Livewire\Concerns\ResolvesGuardianChild;
use App\Services\GuardianOverview;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ringkasan anak')] class extends Component {
    use ResolvesGuardianChild;

    public function with(GuardianOverview $overview): array
    {
        $child = $this->child();

        return [
            'children' => $this->children(),
            'child' => $child,
            'summary' => $child ? $overview->summary($child) : null,
            'classroom' => $child?->activeClassroom(),
            'dormContact' => setting('program.dorm_contact'),
            'pending' => DB::table('guardian_participant')->where('guardian_id', auth()->id())->whereNull('verified_at')->count(),
        ];
    }
}; ?>

<div class="mx-auto max-w-2xl">
    <x-page-header title="Ringkasan anak" subtitle="Kabar terbaru dari kelas dan asrama." />

    @if ($pending)
        <flux:callout icon="clock" color="amber" class="mb-4" :text="$pending.' penautan anak sedang menunggu verifikasi panitia.'" />
    @endif

    @if (! $child)
        <x-empty-state icon="link" title="Belum ada anak terhubung" description="Tautkan anak dengan kode dari panitia atau dari profil anak.">
            <flux:button :href="route('guardian.link')" wire:navigate variant="primary">Hubungkan anak</flux:button>
        </x-empty-state>
    @else
        <x-guardian.child-switcher :children="$children" :child="$child" />

        <x-card class="mb-4">
            <div class="flex items-center gap-3">
                <flux:avatar :name="$child->name" :initials="$child->initials()" />
                <div class="min-w-0">
                    <p class="font-display text-lg font-bold leading-tight">{{ $child->name }}</p>
                    <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $classroom?->label() ?? 'Belum ada kelas' }}</p>
                    <p class="text-sm text-hutan/60 dark:text-santan/60">{{ $summary['room']?->dormitory?->name ?? 'Belum di asrama' }}@if ($summary['room']) · Kamar {{ $summary['room']->number }}@endif</p>
                </div>
            </div>
        </x-card>

        <div class="mb-4 grid grid-cols-2 gap-3">
            <x-card>
                <p class="text-xs font-semibold uppercase text-hutan/60 dark:text-santan/60">Kelas hari ini</p>
                <div class="mt-1">
                    @if ($summary['classToday'])
                        <x-status-badge :status="$summary['classToday']->status" />
                    @else
                        <flux:badge size="sm">Belum absen</flux:badge>
                    @endif
                </div>
            </x-card>
            <x-card>
                <p class="text-xs font-semibold uppercase text-hutan/60 dark:text-santan/60">Asrama malam ini</p>
                <div class="mt-1">
                    @if ($summary['dormToday'] && $summary['dormToday']->status !== \App\Enums\DormAttendanceStatus::Rejected)
                        <x-status-badge :status="$summary['dormToday']->status" />
                    @else
                        <flux:badge size="sm">Belum absen</flux:badge>
                    @endif
                </div>
            </x-card>
            <x-stat label="Hadir" hint="30 hari" :value="$summary['attendanceRate'] !== null ? $summary['attendanceRate'].'%' : '–'" icon="calendar-days" :href="route('guardian.attendance', ['anak' => $child->id])" />
            <x-stat label="Tugas" hint="masuk" :value="$summary['assignmentRate'] !== null ? $summary['assignmentRate'].'%' : '–'" icon="clipboard-document-check" tone="kunyit" :href="route('guardian.grades', ['anak' => $child->id])" />
        </div>

        @foreach ($summary['extras'] as $extra)
            @includeIf($extra['view'] ?? '', $extra)
        @endforeach

        <x-card title="Izin keluar asrama" class="mb-4">
            <x-slot:action>
                <flux:button size="sm" :href="route('guardian.approvals', ['anak' => $child->id])" wire:navigate>Lihat</flux:button>
            </x-slot:action>
            @forelse ($summary['exits'] as $permit)
                <div class="flex items-center justify-between gap-2 border-t border-woka-100 py-2 text-sm first:border-0 dark:border-hutan-800" wire:key="ex-{{ $permit->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $permit->type->label() }} · {{ $permit->destination }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ $permit->periodLabel() }}</p>
                    </div>
                    <x-status-badge :status="$permit->status" />
                </div>
            @empty
                <p class="text-sm text-hutan/60 dark:text-santan/60">Belum ada izin keluar.</p>
            @endforelse
        </x-card>

        <x-card title="Laporan sakit" class="mb-4">
            @forelse ($summary['health'] as $report)
                <a href="{{ route('health.show', $report) }}" wire:navigate class="flex items-center justify-between gap-2 border-t border-woka-100 py-2 text-sm first:border-0 dark:border-hutan-800" wire:key="hr-{{ $report->id }}">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ implode(', ', $report->symptomLabels()) ?: 'Sinyal darurat' }}</p>
                        <p class="text-xs text-hutan/60 dark:text-santan/60">{{ tanggal_jam($report->reported_at) }}</p>
                    </div>
                    <x-status-badge :status="$report->status" />
                </a>
            @empty
                <p class="text-sm text-hutan/60 dark:text-santan/60">Tidak ada laporan sakit. Puji Tuhan!</p>
            @endforelse
        </x-card>

        @if ($dormContact)
            <flux:button icon="phone" class="w-full" :href="Phone::telLink(Phone::normalize($dormContact))">Hubungi pengelola asrama</flux:button>
        @endif
    @endif
</div>
