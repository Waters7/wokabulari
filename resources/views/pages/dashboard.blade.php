<?php

use App\Enums\Role;
use App\Support\Greeting;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    public function with(): array
    {
        $user = auth()->user();

        return [
            'user' => $user,
            'role' => $user->primaryRole(),
            'greeting' => Greeting::forNow($user->name),
            'tagline' => Greeting::tagline(),
        ];
    }
}; ?>

<div class="space-y-5">
    <section class="relative overflow-hidden rounded-3xl bg-hutan p-5 text-santan sm:p-7">
        <div aria-hidden="true" class="pointer-events-none absolute -end-6 -top-8 select-none font-display text-[7rem] font-extrabold leading-none text-white/[0.05]">A中あÄ</div>
        <p class="text-sm font-semibold text-kunyit">{{ tanggal_panjang(now()) }}</p>
        <h1 class="mt-1 font-display text-2xl font-extrabold sm:text-3xl">{{ $greeting }}</h1>
        <p class="mt-1 text-santan/80">{{ $tagline }}</p>
        @if (session('status'))
            <p class="mt-3 inline-flex rounded-full bg-white/10 px-3 py-1 text-sm font-semibold">{{ session('status') }}</p>
        @endif
    </section>

    @switch($role)
        @case(Role::Participant)
            @includeIf('dashboard.participant')
            @break
        @case(Role::Teacher)
            @includeIf('dashboard.teacher')
            @break
        @case(Role::Staff)
            @includeIf('dashboard.staff')
            @break
        @case(Role::Guardian)
            @includeIf('dashboard.guardian')
            @break
        @case(Role::Leader)
            @includeIf('dashboard.leader')
            @break
        @default
            @includeIf('dashboard.admin')
    @endswitch

    @include('dashboard.announcements')
</div>
