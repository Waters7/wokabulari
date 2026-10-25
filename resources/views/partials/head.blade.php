<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' · WOKABULARI' : 'WOKABULARI · Kaya Kosakata, Torang Go Global!' }}
</title>

<meta name="description" content="Wokabulari — Kelas Bahasa SMK Go Global · Poltekkes Kemenkes Manado" />
<meta name="theme-color" content="#007D71" />
<meta name="application-name" content="Wokabulari" />
<meta name="copyright" content="{{ \App\Support\Haki::notice() }}" />
<meta name="wokabulari-haki" content="{{ \App\Support\Haki::fingerprint() }}" data-version="{{ config('wokabulari.version') }}" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="Wokabulari" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />

<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
    // Tampilan bawaan terang. Pilihan gelap/sistem dari Pengaturan → Tampilan tetap dihormati
    // (Flux menghapus kunci saat "sistem" dipilih, jadi pilihan pengguna ditandai terpisah).
    try {
        if (! localStorage.getItem('flux.appearance') && ! localStorage.getItem('wokabulari.appearance-chosen')) {
            localStorage.setItem('flux.appearance', 'light');
        }
    } catch (e) {}
</script>
@fluxAppearance
@stack('head')
