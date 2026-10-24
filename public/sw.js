/* Wokabulari service worker: cache aset statis, halaman offline, dan materi yang sudah dibuka. */
const VERSION = 'wk-v1';
const STATIC_CACHE = `${VERSION}-static`;
const MATERIAL_CACHE = 'wk-materials';
const OFFLINE_URL = '/offline';

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/favicon.svg',
    '/images/brand/logo-horizontal.svg',
    '/images/brand/logo-mark.svg',
    '/images/brand/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('wk-v') && !key.startsWith(VERSION)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Kunci cache materi tanpa query string (signed URL berubah tiap kali dibuat).
function materialKey(url) {
    return `${url.origin}${url.pathname}`;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Aset hasil build, gambar merek, dan model wajah: cache-first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/') || url.pathname.startsWith('/models/') || url.pathname.startsWith('/fonts/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
        return;
    }

    // Materi yang dibuka peserta: network-first, simpan salinan untuk akses offline.
    if (url.pathname.startsWith('/materi/') && url.pathname.endsWith('/berkas')) {
        const key = materialKey(url);
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(MATERIAL_CACHE).then((cache) => cache.put(key, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(key)),
        );
        return;
    }

    // Navigasi halaman: network-first dengan cadangan halaman offline.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});
