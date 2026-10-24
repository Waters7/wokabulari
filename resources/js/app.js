// Wokabulari — skrip dasar. Modul berat (kamera, QR, grafik, peta) dimuat saat dibutuhkan.

// Daftar modul lazy: Wokabulari.load('nama') mengembalikan modul hasil import().
const modules = {};

window.Wokabulari = {
    async load(name) {
        if (!modules[name]) {
            throw new Error(`Modul ${name} tidak ditemukan`);
        }

        return modules[name]();
    },
};

document.addEventListener('alpine:init', () => {
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
