@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-svh bg-santan text-hutan antialiased dark:bg-hutan-950 dark:text-santan">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            {{-- Panel identitas (desktop) --}}
            <aside class="relative hidden overflow-hidden bg-hutan p-10 text-santan lg:flex lg:flex-col lg:justify-between xl:p-14">
                <div aria-hidden="true" class="pointer-events-none absolute -end-10 -top-10 select-none font-display text-[12rem] font-extrabold leading-none text-white/[0.04]">
                    A中あÄ
                </div>

                <a href="{{ route('home') }}" class="relative">
                    <img src="{{ asset('images/brand/logo-horizontal-dark.svg') }}" alt="Wokabulari" class="h-auto w-72">
                </a>

                <div class="relative max-w-md">
                    <p class="font-display text-5xl font-extrabold leading-[1.05]">
                        Kaya Kosakata,<br>
                        <span class="text-kunyit">Torang Go Global!</span>
                    </p>
                    <p class="mt-4 text-lg text-santan/80">
                        Kelas Bahasa SMK Go Global · Poltekkes Kemenkes Manado
                    </p>
                    <ul class="mt-8 flex flex-wrap gap-2 text-sm font-semibold">
                        <li class="rounded-full bg-white/10 px-3 py-1.5">English</li>
                        <li class="rounded-full bg-white/10 px-3 py-1.5" lang="zh">中文</li>
                        <li class="rounded-full bg-white/10 px-3 py-1.5" lang="ja">日本語</li>
                        <li class="rounded-full bg-white/10 px-3 py-1.5">Deutsch</li>
                    </ul>
                </div>

                <x-partner-logos class="relative [&>p]:text-santan/70" />
            </aside>

            {{-- Formulir --}}
            <main class="flex flex-col items-center justify-center px-4 py-8 sm:px-8">
                <div class="w-full max-w-sm">
                    <div class="mb-6 text-center lg:hidden">
                        <a href="{{ route('home') }}" class="inline-block">
                            <img src="{{ asset('images/brand/logo-horizontal.svg') }}" alt="Wokabulari" class="mx-auto h-auto w-56 dark:hidden">
                            <img src="{{ asset('images/brand/logo-horizontal-dark.svg') }}" alt="Wokabulari" class="mx-auto hidden h-auto w-56 dark:block">
                        </a>
                        <p class="mt-2 font-display text-lg font-bold text-woka dark:text-kunyit">Kaya Kosakata, Torang Go Global!</p>
                    </div>

                    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-woka-100 sm:p-8 dark:bg-hutan-900 dark:ring-hutan-800">
                        {{ $slot }}
                    </div>

                    <x-partner-logos class="mt-8 lg:hidden" compact />

                    <p class="mt-6 text-center text-xs text-hutan/60 dark:text-santan/50">
                        &copy; {{ now()->year }} Wokabulari · Program SMK Go Global
                    </p>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
