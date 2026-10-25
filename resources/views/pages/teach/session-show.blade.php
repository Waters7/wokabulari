<?php

use App\Actions\Sessions\EndSession;
use App\Actions\Sessions\ManageSessionCode;
use App\Enums\SessionStatus;
use App\Models\LearningSession;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Detail sesi')] class extends Component {
    public LearningSession $session;

    #[Url]
    public string $tab = 'kehadiran';

    public function mount(LearningSession $session): void
    {
        $this->authorize('view', $session);
        $this->session = $session->load(['classroom.language', 'teacher']);
    }

    public function start(ManageSessionCode $codes): void
    {
        $this->authorize('update', $this->session);

        try {
            $codes->start($this->session, auth()->user());
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->session->refresh();
        $this->tab = 'kehadiran';
        $this->dispatch('session-updated');
        Flux::toast(variant: 'success', text: 'Sesi dimulai. Tampilkan kode ke peserta!');
    }

    public function end(EndSession $end): void
    {
        $this->authorize('update', $this->session);

        if ($this->session->status !== SessionStatus::Ongoing) {
            return;
        }

        $count = $end->handle($this->session, auth()->user());
        $this->session->refresh();
        $this->dispatch('session-updated');
        Flux::toast(variant: 'success', text: "Sesi selesai. {$count} peserta yang belum input kode ditandai otomatis.");
    }

    public function with(): array
    {
        $tabs = ['kehadiran' => 'Kehadiran', 'materi' => 'Materi'];

        // Tab tambahan dari fase berikutnya (penilaian, catatan) tampil bila komponennya ada.
        foreach (['penilaian' => ['Penilaian', 'session.assessment-panel'], 'catatan' => ['Catatan', 'session.notes-panel']] as $key => [$label, $component]) {
            if (view()->exists('livewire.'.$component)) {
                $tabs[$key] = $label;
            }
        }

        return [
            'tabs' => $tabs,
            'canManage' => auth()->user()->can('update', $this->session),
        ];
    }
}; ?>

<div>
    <x-page-header :title="$session->topic" :back="route('teach.sessions.index')"
                   :subtitle="tanggal_panjang($session->date).' · '.$session->timeRange().' · '.$session->classroom->label()">
        <x-slot:actions>
            <x-status-badge :status="$session->status" size="lg" />
            @if ($canManage)
                @if ($session->status === SessionStatus::Scheduled)
                    <flux:button variant="primary" icon="play" wire:click="start">Mulai sesi</flux:button>
                @elseif ($session->status === SessionStatus::Ongoing)
                    <x-confirm-button action="end" label="Selesai sesi" icon="stop" variant="danger"
                                      title="Selesaikan sesi?" message="Peserta yang belum input kode akan ditandai alpa, kecuali punya izin/sakit yang disetujui." confirmLabel="Ya, selesai" size="base" />
                @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($session->objectives)
        <flux:callout icon="light-bulb" class="mb-4" heading="Tujuan pembelajaran" :text="$session->objectives" />
    @endif

    <div class="mb-4 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 ring-1 ring-woka-100 dark:bg-hutan-900 dark:ring-hutan-800" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['min-h-11 flex-1 whitespace-nowrap rounded-lg px-2.5 text-sm font-semibold transition sm:px-4', 'bg-woka text-white' => $tab === $key, 'text-hutan/70 dark:text-santan/70 hover:bg-woka-50 dark:text-santan/70 dark:hover:bg-hutan-800' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @switch($tab)
        @case('materi')
            <livewire:session.materials-panel :session="$session" :key="'materials-'.$session->id" />
            @break
        @case('penilaian')
            <livewire:session.assessment-panel :session="$session" :key="'assessment-'.$session->id" />
            @break
        @case('catatan')
            <livewire:session.notes-panel :session="$session" :key="'notes-'.$session->id" />
            @break
        @default
            <livewire:session.attendance-panel :session="$session" :key="'attendance-'.$session->id.'-'.$session->status->value" />
    @endswitch
</div>
