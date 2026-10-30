// Wokabulari — skrip dasar. Modul berat (kamera, QR, grafik, peta) dimuat saat dibutuhkan.

// Daftar modul lazy: Wokabulari.load('nama') mengembalikan modul hasil import().
import audioRecorder from './components/audio-recorder.js';
import classCamera from './components/class-camera.js';
import faceCapture from './components/face-capture.js';
import signaturePad from './components/signature-pad.js';

const modules = {
    map: () => import('./modules/map.js'),
    face: () => import('./modules/face.js'),
    qr: () => import('./modules/qr-scanner.js'),
    charts: () => import('./modules/charts.js'),
    shareCard: () => import('./modules/share-card.js'),
};

window.Wokabulari = {
    async load(name) {
        if (!modules[name]) {
            throw new Error(`Modul ${name} tidak ditemukan`);
        }

        return modules[name]();
    },
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('faceCapture', faceCapture);
    window.Alpine.data('audioRecorder', audioRecorder);
    window.Alpine.data('signaturePad', signaturePad);
    window.Alpine.data('classCamera', classCamera);

    // Hitung mundur ke waktu tertentu (ISO string). Dipakai di kode sesi, quiz, dan tenggat.
    window.Alpine.data('countdown', (target, onDone = null) => ({
        remaining: 0,
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
        },
        tick() {
            this.remaining = Math.max(0, Math.floor((new Date(target).getTime() - Date.now()) / 1000));
            if (this.remaining === 0) {
                clearInterval(this.timer);
                if (onDone) {
                    this.$dispatch(onDone);
                }
            }
        },
        get display() {
            const h = Math.floor(this.remaining / 3600);
            const m = Math.floor((this.remaining % 3600) / 60);
            const s = this.remaining % 60;
            const pad = (n) => String(n).padStart(2, '0');
            return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        },
    }));

    // Input rating 1–5 (ikon bintang/hati) yang terhubung ke wire:model.
    window.Alpine.data('ratingInput', (model, max = 5) => ({
        value: model,
        hover: 0,
        max,
        set(n) {
            this.value = n;
        },
        filled(n) {
            return (this.hover || this.value) >= n;
        },
    }));

    // Peta pemilih titik lokasi; nilai lat/lng ditulis ke properti Livewire.
    window.Alpine.data('mapPicker', ({ lat, lng, radius, latModel, lngModel, editable = true }) => ({
        picker: null,
        async init() {
            const { createPicker } = await window.Wokabulari.load('map');
            this.picker = createPicker(this.$refs.map, {
                lat,
                lng,
                radius,
                editable,
                onChange: (newLat, newLng) => {
                    this.$wire.set(latModel, newLat, false);
                    this.$wire.set(lngModel, newLng, false);
                },
            });
        },
        destroy() {
            this.picker?.destroy();
        },
        setRadius(value) {
            this.picker?.setRadius(value);
        },
        useMyLocation() {
            navigator.geolocation?.getCurrentPosition((pos) => {
                const { latitude, longitude } = pos.coords;
                this.picker?.setLatLng(latitude, longitude);
                this.$wire.set(latModel, Number(latitude.toFixed(7)), false);
                this.$wire.set(lngModel, Number(longitude.toFixed(7)), false);
            });
        },
    }));

    // Pemindai QR: hasil pindaian diteruskan ke callback (mis. isi kode sesi / token izin keluar).
    window.Alpine.data('qrScanner', (onResult) => ({
        scanning: false,
        error: null,
        handle: null,
        async start() {
            this.error = null;
            this.scanning = true;
            await this.$nextTick();
            try {
                const { startScanner } = await window.Wokabulari.load('qr');
                this.handle = await startScanner(this.$refs.reader.id, async (text) => {
                    await this.stop();
                    onResult.call(this, text);
                });
            } catch (e) {
                this.scanning = false;
                this.error = 'Kamera tidak dapat dibuka. Izinkan akses kamera atau ketik kode secara manual.';
            }
        },
        async stop() {
            await this.handle?.stop();
            this.handle = null;
            this.scanning = false;
        },
        destroy() {
            this.handle?.stop();
        },
    }));

    // Kartu lencana untuk dibagikan: <div x-data="badgeShare({...})"> dengan x-ref canvas & template ikon.
    window.Alpine.data('badgeShare', (card) => ({
        format: 'story',
        showName: card.showName ?? true,
        busy: true,
        canShare: false,
        error: null,
        async init() {
            this.$watch('format', () => this.render());
            this.$watch('showName', () => this.render());
            await this.render();
        },
        async render() {
            this.busy = true;
            try {
                const { drawBadgeCard } = await window.Wokabulari.load('shareCard');
                const svg = this.$refs.icon.innerHTML.trim();
                await drawBadgeCard(this.$refs.canvas, card, svg, this.format, this.showName);
                this.canShare = Boolean(navigator.canShare?.({ files: [new File([''], 'x.png', { type: 'image/png' })] }));
                this.error = null;
            } catch (e) {
                this.error = 'Gambar belum bisa dibuat. Coba muat ulang halaman.';
            } finally {
                this.busy = false;
            }
        },
        async blob() {
            const { toBlob } = await window.Wokabulari.load('shareCard');

            return toBlob(this.$refs.canvas);
        },
        async share() {
            const file = new File([await this.blob()], card.fileName, { type: 'image/png' });
            try {
                await navigator.share({ files: [file], text: card.shareText });
            } catch (e) {
                // Dibatalkan pengguna: tidak perlu pesan.
            }
        },
        async download() {
            const url = URL.createObjectURL(await this.blob());
            const link = Object.assign(document.createElement('a'), { href: url, download: card.fileName });
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },
    }));

    // Grafik Chart.js: <div x-data="chart({type, data})"><canvas x-ref="canvas"></canvas></div>
    window.Alpine.data('chart', (config) => ({
        instance: null,
        async init() {
            const { createChart } = await window.Wokabulari.load('charts');
            this.instance = createChart(this.$refs.canvas, config);
        },
        destroy() {
            this.instance?.destroy();
        },
    }));

    // Peta baca-saja.
    window.Alpine.data('mapViewer', (points) => ({
        map: null,
        async init() {
            const { createViewer } = await window.Wokabulari.load('map');
            this.map = createViewer(this.$refs.map, points);
        },
        destroy() {
            this.map?.remove();
        },
    }));

    // Ambil lokasi GPS sekali dengan akurasi tinggi.
    window.Alpine.data('geolocate', () => ({
        status: 'idle',
        coords: null,
        error: null,
        locate() {
            if (!('geolocation' in navigator)) {
                this.status = 'error';
                this.error = 'Perangkat tidak mendukung GPS.';
                return Promise.reject(this.error);
            }

            this.status = 'loading';

            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.coords = {
                            latitude: pos.coords.latitude,
                            longitude: pos.coords.longitude,
                            accuracy: pos.coords.accuracy,
                        };
                        this.status = 'ready';
                        resolve(this.coords);
                    },
                    (err) => {
                        this.status = 'error';
                        this.error = err.code === 1 ? 'Izin lokasi ditolak. Aktifkan lokasi di pengaturan browser.' : 'Lokasi tidak dapat diambil. Coba lagi di tempat terbuka.';
                        reject(this.error);
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 },
                );
            });
        },
    }));
});

// Service worker untuk PWA (cache aset statis, halaman offline, materi yang sudah dibuka).
if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
