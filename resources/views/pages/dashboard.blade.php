<?php

use App\Enums\Role;
use App\Support\Greeting;
use App\Support\Navigation;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Beranda')] class extends Component {
    /** Menu yang paling sering dipakai per peran, diurutkan lebih dulu (hanya yang boleh diakses). */
    protected const PRIORITY = [
        'teach.sessions.index', 'teach.grading', 'manage.attendance.index', 'teach.assignments.index',
        'master.participants', 'master.classrooms', 'manage.evaluations.index', 'manage.announcements',
        'leader.dashboard', 'reports.index', 'manage.leave.index', 'meals.ratings', 'manage.teacher-ratings',
    ];

    /**
     * @param  list<array{heading: string, items: list<array<string, mixed>>}>  $groups
     * @return list<array<string, mixed>>
     */
    protected function shortcuts(array $groups): array
    {
        $items = collect($groups)->flatMap(fn (array $group) => $group['items'])
            ->reject(fn (array $item) => $item['route'] === 'dashboard')
            ->unique('route');

        return $items->sortBy(fn (array $item) => array_search($item['route'], self::PRIORITY, true) === false ? 100 : array_search($item['route'], self::PRIORITY, true))
            ->take(6)->values()->all();
    }

    public function with(Navigation $navigation): array
    {
        $user = auth()->user();
        $role = $user->primaryRole();

        return [
            'user' => $user,
            'role' => $role,
            'greeting' => Greeting::forNow($user->name),
            'tagline' => Greeting::tagline(),
            // Peran tanpa bottom nav (admin, pengajar, pimpinan) mendapat akses cepat ke menu utamanya.
            'shortcuts' => $role?->usesBottomNav() ? [] : $this->shortcuts($navigation->sidebar($user)),
        ];
    }
}; ?>

{{-- Beranda minimalis: sapaan tanpa banner, kartu datar, aksen warna seperlunya. --}}
<div class="mx-auto max-w-3xl space-y-6">
    <header class="pt-1">
        <p class="text-sm text-hutan/55 dark:text-santan/55">{{ tanggal_panjang(now()) }}</p>
        <h1 class="mt-1 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $greeting }}</h1>
        <p class="mt-0.5 text-sm text-hutan/60 dark:text-santan/60">{{ $tagline }}</p>
        @if (session('status'))
            <p class="mt-3 flex items-center gap-1.5 text-sm font-medium text-woka dark:text-woka-light">
                <flux:icon.check-circle class="size-4 shrink-0" /> {{ session('status') }}
            </p>
        @endif
    </header>

    @include('dashboard.evaluations')

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

    @if ($shortcuts)
        <section>
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-hutan/45 dark:text-santan/45">Akses cepat</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($shortcuts as $item)
                    <a href="{{ $item['url'] }}" wire:navigate wire:key="sc-{{ $item['route'] }}"
                       class="flex min-w-0 items-center gap-2.5 rounded-2xl bg-white px-3 py-3 text-sm font-medium ring-1 ring-zinc-200/70 transition hover:ring-woka-200 dark:bg-hutan-900 dark:ring-hutan-800">
                        <flux:icon :name="$item['icon']" class="size-5 shrink-0 text-woka dark:text-woka-light" />
                        <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @include('dashboard.announcements')
</div>
