# WOKABULARI — Panduan Proyek untuk Claude Code

Aplikasi web kelas bahasa (Inggris, Mandarin, Jepang, Jerman) untuk program **SMK Go Global**, diselenggarakan oleh **Poltekkes Kemenkes Manado**. Mobile-first, dapat di-install sebagai PWA.

Nama "Wokabulari" adalah plesetan dari *woku* (masakan khas Manado) dan *vocabulary*. Tagline: **"Kaya Kosakata, Torang Go Global!"**

## Dokumen acuan (baca sebelum bekerja)

| File | Isi |
|---|---|
| `docs/SPEC.md` | Spesifikasi lengkap: role, fitur, skema database, laporan, keamanan |
| `docs/DOCKER.md` | Lingkungan Docker untuk localhost dan produksi VPS |
| `docs/PLAN.md` | Tahapan pengerjaan (fase) dengan checklist |
| `docs/DECISIONS.md` | Catatan keputusan teknis yang menyimpang dari SPEC (buat jika belum ada) |
| `docs/brand/` | Logo SVG/PNG dan ikon PWA |

## Stack

- **Laravel 13** (PHP 8.4 di container; minimum 8.3), **MySQL 8.4**, **Redis 7**
- **Livewire 4** + Blade + Alpine.js, **Tailwind CSS v4** via Vite
- Auth: starter kit Livewire resmi Laravel, login memakai email **atau** NIS/NIP
- Paket: spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-backup, maatwebsite/excel, barryvdh/laravel-dompdf, simplesoftwareio/simple-qrcode
- JS: @vladmandic/face-api (model di `public/models`), signature_pad, chart.js, html5-qrcode, MediaRecorder API
- Test: **Pest**
- Semua berjalan di **Docker**, baik di localhost maupun di VPS

Jika sebuah paket belum kompatibel dengan Laravel 13, pilih alternatif setara dan catat di `docs/DECISIONS.md`.

## Perintah harian

Semua perintah lewat Docker. **Jangan** menjalankan `php`, `composer`, atau `npm` langsung di host.

```bash
make up                         # nyalakan semua container dev
make down                       # matikan
make sh                         # shell di container app
make artisan cmd="migrate"      # jalankan perintah artisan
make composer cmd="require x/y" # composer
make npm cmd="install"          # npm (container node)
make test                       # jalankan seluruh test Pest
make fresh                      # migrate:fresh --seed
make logs                       # ikuti log semua service
```

Alamat lokal: aplikasi `http://localhost:8080`, Mailpit `http://localhost:8025`, phpMyAdmin `http://localhost:8081` (profil `tools`).

## Aturan kerja

1. Kerjakan sesuai `docs/PLAN.md`, **satu fase per permintaan**. Sebelum mulai, baca bagian SPEC yang relevan dan sampaikan rencana singkat fase tersebut.
2. Setiap fitur wajib punya: migration, model + relasi, factory, Policy, Form Request, dan Feature Test.
3. Fase dianggap selesai hanya jika `make test` lulus dan halaman terkait sudah dicek di lebar layar 360px.
4. Setelah fase selesai, centang checklist di `docs/PLAN.md` dan tulis ringkasan singkat perubahan.
   Setiap perubahan yang akan dirilis: naikkan `version` di `config/wokabulari.php` (patch = perbaikan, minor = fitur) dan catat di `docs/CHANGELOG.md`. Versi tampil di halaman login.
5. Commit kecil per fitur, pesan commit Bahasa Indonesia: `feat: absen harian dengan verifikasi wajah`, `fix: ...`, `test: ...`.
6. Keputusan yang menyimpang dari SPEC dicatat di `docs/DECISIONS.md` beserta alasannya.
7. Untuk aturan bisnis yang ambigu, tanyakan dulu. Jangan menebak untuk hal yang sulit dibatalkan (menghapus data, mengubah skema yang sudah berisi data).

## Konvensi kode

- **Bahasa:** teks UI Bahasa Indonesia; nama kelas, tabel, kolom, dan variabel dalam Bahasa Inggris; komentar singkat Bahasa Indonesia.
- **Enum:** semua status memakai PHP backed enum (nilai Bahasa Inggris) dengan method `label()` untuk teks Bahasa Indonesia dan `color()` untuk badge.
- **Struktur:** logika bisnis di `app/Actions` atau `app/Services`; controller dan komponen Livewire tetap tipis. Gamifikasi dan notifikasi lewat Event + Listener. Ekspor besar dan notifikasi lewat Job antrean (Redis).
- **Otorisasi:** Policy untuk setiap model; middleware role/permission di route. Role pimpinan selalu read-only.
- **Waktu:** `APP_TIMEZONE=Asia/Makassar` (WITA). Format tanggal Indonesia (`24 Okt 2026`, `Sabtu, 24 Oktober 2026`).
- **File privat** (selfie, tanda tangan, audio, lampiran sakit, tugas) di disk `private`, diakses lewat route terproteksi atau signed URL. Hanya materi dan aset publik di disk `public`.
- **UI:** mobile-first; komponen Blade reusable di `resources/views/components`; ikuti palet dan huruf di SPEC bagian 1.
- **Data sensitif** (riwayat penyakit, data kesehatan, face descriptor) memakai cast `encrypted`.

## Pengujian di localhost

- Kamera, mikrofon, dan GPS butuh *secure context*. `http://localhost` sudah dianggap aman oleh browser, jadi bisa diuji langsung di laptop.
- Lokasi palsu untuk uji geofence: Chrome DevTools → More tools → Sensors → Location.
- Untuk uji dari HP, pakai tunnel HTTPS (`make tunnel`) seperti dijelaskan di `docs/DOCKER.md`.
- Variabel `.env` khusus pengembangan: `FACE_VERIFICATION_ENABLED=false` dan `GEOFENCE_ENABLED=false` boleh dipakai untuk mempercepat uji, tetapi **wajib `true` di produksi**.
- Notifikasi WhatsApp di lokal memakai driver `log`; email tertangkap di Mailpit.

## Larangan

- Jangan commit `.env`, kredensial, atau file di `storage/app/private`.
- Jangan pernah mengirim kunci jawaban quiz ke browser sebelum attempt dikumpulkan.
- Jangan menyimpan selfie, tanda tangan, atau data kesehatan di disk publik.
- Jangan membuka port MySQL atau Redis ke internet di konfigurasi produksi.
- Jangan menonaktifkan validasi lokasi/waktu di server; validasi browser hanya pelengkap.
