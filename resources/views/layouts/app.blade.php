@props(['title' => null])
@php
    $user = auth()->user();
    $navigation = app(\App\Support\Navigation::class);
    $groups = $navigation->sidebar($user);
    $bottomItems = $navigation->bottom($user);
    $unread = $user->unreadNotifications()->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-santan text-hutan antialiased dark:bg-hutan-950 dark:text-santan">
        <div aria-hidden="true" class="fixed inset-x-0 top-0 z-50 h-1 bg-kemenkes-stripe"></div>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-woka-100 bg-white dark:border-hutan-800 dark:bg-hutan-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @foreach ($groups as $group)
                    <flux:sidebar.group :heading="$group['heading']" class="grid">
                        @foreach ($group['items'] as $item)
                            <flux:sidebar.item :icon="$item['icon']" :href="$item['url']" :current="$item['active']" wire:navigate>
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" />
            <p class="px-2 text-[11px] text-hutan/50 dark:text-santan/50">Wokabulari versi {{ config('wokabulari.version') }}</p>
        </flux:sidebar>

        {{-- Header HP --}}
        <flux:header class="sticky top-0 z-30 border-b border-woka-100 bg-white/95 backdrop-blur lg:hidden dark:border-hutan-800 dark:bg-hutan-900/95">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="Buka menu" />

            {{-- Teks logo diperkecil di layar 360px agar muat bersama lonceng, profil, dan tombol keluar --}}
            <x-app-logo href="{{ route('dashboard') }}" class="ms-1 min-w-0 [&>span]:text-lg min-[400px]:[&>span]:text-xl" wire:navigate />

            <flux:spacer />

            <div class="flex shrink-0 items-center gap-1">
                <x-notification-bell :count="$unread" />

                <flux:dropdown position="top" align="end">
                    <flux:profile :initials="$user->initials()" :avatar="$user->photo_url" :chevron="false" />
                    <x-user-menu-items />
                </flux:dropdown>

                <x-logout-button compact />
            </div>
        </flux:header>

        <flux:main @class(['max-w-7xl', 'has-bottom-nav' => count($bottomItems) > 0])>
            {{-- Bilah kanan atas (desktop): notifikasi, profil, keluar --}}
            <div class="mb-3 hidden items-center justify-end gap-2 lg:flex">
                <x-notification-bell :count="$unread" />
                <flux:dropdown position="bottom" align="end">
                    <flux:profile :name="$user->name" :initials="$user->initials()" :avatar="$user->photo_url" icon-trailing="chevron-down" />
                    <x-user-menu-items />
                </flux:dropdown>
                <x-logout-button />
            </div>

            {{ $slot }}
        </flux:main>

        @if (count($bottomItems) > 0)
            <x-bottom-nav :items="$bottomItems" />
        @endif

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @stack('scripts')
    </body>
</html>
