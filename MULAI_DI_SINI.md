# Mulai di Sini: Membangun Wokabulari dengan Claude Code

## Isi paket

```
wokabulari/
├── CLAUDE.md          ← dibaca otomatis oleh Claude Code setiap sesi
├── MULAI_DI_SINI.md   ← file ini (untuk Anda)
└── docs/
    ├── SPEC.md        ← spesifikasi lengkap aplikasi
    ├── DOCKER.md      ← lingkungan Docker: localhost → VPS
    ├── PLAN.md        ← 16 fase pengerjaan dengan checklist
    └── brand/         ← logo SVG/PNG dan ikon aplikasi
```

## Persiapan (sekali saja)

1. Pasang **Docker Desktop** (Windows/macOS) atau **Docker Engine** (Linux). Di Windows, aktifkan WSL2 dan simpan proyek di dalam WSL agar cepat.
2. Pasang **Git** dan **Claude Code** (panduan resmi: https://docs.claude.com).
3. Buat folder proyek kosong, misalnya `wokabulari`, lalu ekstrak isi paket ini ke dalamnya.
4. Buka terminal di folder tersebut, jalankan `git init`, lalu jalankan `claude`.

## Prompt pertama (salin persis)

```
Baca CLAUDE.md, lalu docs/SPEC.md, docs/DOCKER.md, dan docs/PLAN.md.

Kita mulai Fase 0. Sebelum menulis kode, jelaskan rencana Fase 0 secara singkat:
struktur folder, paket yang akan dipasang, serta isi Dockerfile dan compose.yaml.
Tunggu persetujuan saya.

Setelah saya setuju, kerjakan Fase 0 sampai kriteria "Selesai jika" terpenuhi,
jalankan make test, centang checklist di docs/PLAN.md, tulis ringkasan, lalu commit.
```

## Prompt untuk fase berikutnya

Ganti `N` dengan nomor fase:

```
Lanjut Fase N sesuai docs/PLAN.md. Baca bagian SPEC yang disebut di fase itu,
sampaikan rencana singkat, dan tunggu persetujuan saya.
Setelah dikerjakan: jalankan make test, cek tampilan di lebar 360px,
centang checklist, tulis ringkasan di Catatan Progres, lalu commit.
```

Setelah setiap fase, minta panduan uji:

```
Tunjukkan cara saya menguji Fase N di localhost: akun seeder yang dipakai,
halaman yang dibuka, dan skenario yang harus dicoba (termasuk skenario gagal).
```

## Menguji di localhost

- Aplikasi: `http://localhost:8080` · Email uji: `http://localhost:8025`
- Akun contoh ada di `docs/SPEC.md` bagian 8 (password `password`, hanya untuk lokal).
- Kamera dan GPS bisa langsung diuji di laptop karena `localhost` dianggap aman oleh browser.
- Untuk mencoba dari HP, minta Claude Code menjalankan `make tunnel` (lihat `docs/DOCKER.md` bagian B.6).

## Menuju VPS

Setelah Fase 0–14 selesai dan uji di localhost memuaskan:

```
Kerjakan Fase 15. Mulai dengan uji mode produksi di localhost (make prod-local)
sesuai docs/DOCKER.md bagian C. Setelah lulus, tuliskan langkah deploy VPS
di README.md berdasarkan bagian D, dengan domain [domain-anda].
```

Lalu ikuti langkah D.5 di `docs/DOCKER.md` di server Anda. Siapkan sebelumnya: VPS Ubuntu 24.04 (minimal 2 vCPU, 4 GB RAM), domain yang mengarah ke IP VPS, akun SMTP untuk email, dan (opsional) gateway WhatsApp.

## Tips

- Satu fase per percakapan membuat hasil lebih rapi. Jika percakapan sudah panjang, mulai sesi baru; Claude Code akan membaca ulang `CLAUDE.md` dan progres di `docs/PLAN.md`.
- Jika ingin mengubah fitur, ubah dulu `docs/SPEC.md`, lalu minta Claude Code menyesuaikan kode.
- Simpan repo di GitHub/GitLab privat sejak awal agar mudah dipindah ke VPS.
