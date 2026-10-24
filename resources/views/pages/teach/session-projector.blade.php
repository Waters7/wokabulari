<?php

use App\Models\LearningSession;
use App\Services\QrCodeService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::projector')] #[Title('Mode proyektor')] class extends Component {
    public LearningSession $session;

    public function mount(LearningSession $session): void
    {
        $this->authorize('update', $session);
        $this->session = $session->load('classroom.language');
    }

    public function with(): array
    {
        $this->session->refresh();
        $code = $this->session->isOngoing() ? $this->session->activeCode()->first() : null;

        return [
            'code' => $code,
            'qr' => $code ? app(QrCodeService::class)->svg(route('sessions.code', ['kode' => $code->code]), 520, 2) : null,
            'submitted' => $this->session->attendances()->whereIn('status', ['present', 'late'])->count(),
            'total' => $this->session->classroom->participants()->count(),
        ];
    }
}; ?>

<div wire:poll.5s class="flex min-h-full flex-col p-6 lg:p-10" x-data x-on:keydown.f.window="document.documentElement.requestFullscreen?.()">
    <header class="flex items-center justify-between gap-4">
        <img src="{{ asset('images/brand/logo-horizontal-dark.svg') }}" alt="Wokabulari" class="h-14 w-auto">
        <div class="text-end">
            <p class="font-display text-2xl font-bold">{{ $session->classroom->language->flag() }} {{ $session->classroom->label() }}</p>
            <p class="text-santan/70">{{ $session->topic }} · {{ $session->timeRange() }}</p>
        </div>
    </header>

    <main class="flex flex-1 flex-col items-center justify-center gap-8 lg:flex-row lg:gap-16">
        @if ($code)
            <div class="text-center">
                <p class="text-xl font-semibold uppercase tracking-[0.3em] text-kunyit">Kode kehadiran</p>
                <p class="my-4 font-mono text-[18vw] font-extrabold leading-none tracking-[0.15em] lg:text-[11vw]">{{ $code->code }}</p>
                <p class="text-3xl" x-data="countdown('{{ $code->expires_at->toIso8601String() }}')" wire:key="pc-{{ $code->id }}-{{ $code->expires_at->timestamp }}">
                    Berlaku <span class="font-mono font-bold text-kunyit" x-text="display"></span>
                </p>
            </div>
            <div class="w-[min(70vw,28rem)] rounded-3xl bg-white p-4 [&>svg]:h-auto [&>svg]:w-full">{!! $qr !!}</div>
        @else
            <div class="text-center">
                <p class="font-display text-5xl font-extrabold">Kode belum aktif</p>
                <p class="mt-3 text-2xl text-santan/70">Tunggu pengajar membuat kode baru…</p>
            </div>
        @endif
    </main>

    <footer class="flex items-center justify-between text-xl">
        <p>Buka <span class="font-semibold text-kunyit">Wokabulari → Input Kode Sesi</span> atau pindai QR</p>
        <p class="font-display text-3xl font-bold">{{ $submitted }} / {{ $total }} <span class="text-lg font-normal text-santan/70">sudah hadir</span></p>
    </footer>
</div>
