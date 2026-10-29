@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    {{-- Tampilan minimalis: satu kolom di tengah, latar putih, aksen Kemenkes hanya pada garis atas & tombol. --}}
    <body class="min-h-svh bg-white text-hutan antialiased dark:bg-hutan-950 dark:text-santan">
        <div aria-hidden="true" class="fixed inset-x-0 top-0 z-50 h-1 bg-kemenkes-stripe"></div>

        <main class="flex min-h-svh flex-col items-center px-5 pb-8 pt-12 sm:pt-20">
            <div class="flex w-full max-w-sm flex-1 flex-col">
                <a href="{{ route('home') }}" class="mb-10 block self-start" aria-label="Wokabulari — beranda">
                    <img src="{{ asset('images/brand/logo-horizontal.svg') }}" alt="Wokabulari" class="h-auto w-40 dark:hidden">
                    <img src="{{ asset('images/brand/logo-horizontal-dark.svg') }}" alt="Wokabulari" class="hidden h-auto w-40 dark:block">
                </a>

                <div class="flex-1">
                    {{ $slot }}
                </div>

                <footer class="mt-14 space-y-5">
                    <x-partner-logos minimal />
                    <p class="text-center text-[11px] leading-relaxed text-hutan/45 dark:text-santan/40">
                        <span class="font-semibold">Wokabulari versi {{ config('wokabulari.version') }}</span>
                        · &copy; {{ \App\Support\Haki::STATEMENT['tahun'] }} Poltekkes Kemenkes Manado<br>
                        <span data-test="haki">Hak cipta dilindungi · Sidik HaKI {{ \App\Support\Haki::fingerprint() }}</span>
                    </p>
                </footer>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
