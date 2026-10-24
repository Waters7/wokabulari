# RENCANA PENGERJAAN WOKABULARI

Kerjakan **satu fase per permintaan**. Di awal fase, baca bagian SPEC yang disebut dan sampaikan rencana singkat. Di akhir fase: `make test` lulus, halaman dicek di lebar 360px, checklist dicentang, ringkasan perubahan ditulis di bawah fase.

Status: `[ ]` belum · `[x]` selesai

---

## Fase 0 — Fondasi & Docker
Acuan: CLAUDE.md, DOCKER.md bagian A–B, SPEC 1
- [x] Proyek Laravel 13 + starter kit Livewire, Pest
- [x] `docker/app/Dockerfile`, `compose.yaml`, `Makefile`, `.env.example`, `.dockerignore`
- [x] Tailwind v4 dengan token warna & huruf Wokabulari
- [x] Layout mobile (bottom nav) dan desktop (sidebar), komponen Blade dasar (button, card, badge, modal, toast, empty state)
- [x] Logo & ikon dari `docs/brand/` dipasang; manifest PWA + service worker dasar + halaman offline
- [x] Lokalisasi Bahasa Indonesia, zona waktu WITA, helper format tanggal
- [ ] Halaman `/up` sehat; `make install` berjalan dari nol
- **Selesai jika:** `make build && make up && make install` berhasil di mesin bersih dan halaman login tampil dengan identitas Wokabulari.

## Fase 1 — Role, Master Data, Pengaturan
Acuan: SPEC 2, 3.1, 4.1
- [x] Role & permission (spatie), login email/NIS/NIP, `app:create-admin`
- [x] Persetujuan data pribadi saat login pertama
- [x] CRUD angkatan, bahasa, kelas, lokasi (peta Leaflet), asrama, kamar, vendor
- [x] Peserta: CRUD, import Excel + template, profil, pendaftaran wajah referensi (3 sampel)
- [x] Unit tim kerja, pengaturan sistem (tabel `settings`)
- [x] `ProductionSeeder` (data dasar) dan `DemoSeeder` (data contoh, SPEC 8) — DemoSeeder dilengkapi bertahap tiap fase
- [x] Audit log aktif

## Fase 2 — Absen Harian (Wajah + Lokasi)
Acuan: SPEC 3.2
- [ ] Halaman kamera + liveness + pengambilan descriptor (face-api)
- [ ] Pencocokan wajah & geofence di server, status hadir/terlambat/ditolak
- [ ] Persetujuan manual oleh admin/pengajar
- [ ] Riwayat absen peserta

## Fase 3 — Sesi, Kode Kehadiran, Materi, Izin
Acuan: SPEC 3.3, 3.4, 3.5, 3.11
- [ ] Jadwal sesi (termasuk berulang), mulai/selesai sesi
- [ ] Kode + QR, mode proyektor, monitor realtime, penandaan manual & otomatis
- [ ] Input kode / pindai QR peserta + rate limit
- [ ] Materi per sesi, bank materi, log akses, cache offline
- [ ] Izin/sakit kelas + sinkron ke status kehadiran

## Fase 4 — Penilaian Sesi & Periodik
Acuan: SPEC 3.6, 3.9, 3.10
- [ ] Komponen penilaian + template, input skor massal, nilai tertimbang
- [ ] Catatan per sesi (opsi boleh dilihat wali)
- [ ] Penilaian mingguan/bulanan dengan saran nilai, draft/terbit
- [ ] Halaman nilai peserta + grafik

## Fase 5 — Tugas / PR
Acuan: SPEC 3.7
- [ ] Buat tugas, lampiran, jenis pengumpulan
- [ ] Pengumpulan dari HP (foto, file, audio), terlambat & revisi
- [ ] Penilaian cepat + voice note, integrasi ke komponen sesi
- [ ] Pengingat tenggat

## Fase 6 — Quiz, Pretest, Posttest, Tryout
Acuan: SPEC 3.8
- [ ] Bank soal + import Excel, semua tipe soal termasuk hanzi/pinyin & nada
- [ ] Pengerjaan quiz: timer, autosave, auto-submit, deteksi pindah tab
- [ ] Penilaian otomatis & antrean "Perlu Dinilai"
- [ ] N-Gain pretest–posttest, analisis butir soal, tryout format HSK
- [ ] Test: kunci jawaban tidak bocor

## Fase 7 — Konsumsi & Rating
Acuan: SPEC 3.12, 3.13, 3.14
- [ ] Jadwal makan, duplikasi mingguan, daftar alergi
- [ ] Tanda tangan penerimaan + penerimaan diwakilkan, monitor realtime
- [ ] Rating vendor & rating pengajar mingguan (anonim), pengingat Jumat

## Fase 8 — Asrama & Kesehatan
Acuan: SPEC 3.15–3.18
- [ ] Absen asrama malam + konfirmasi/apel manual pengelola
- [ ] Izin keluar asrama + QR keluar/masuk + `late_return`
- [ ] Keluhan & inventaris kamar
- [ ] Laporan sakit + tombol darurat + timeline penanganan + integrasi (izin sakit kelas, makanan khusus, status asrama)

## Fase 9 — Portal Wali
Acuan: SPEC 3.19
- [ ] Undangan & verifikasi wali, login
- [ ] Tampilan anak (kehadiran, nilai, tugas, izin, sakit, poin)
- [ ] Persetujuan wali untuk izin keluar
- [ ] Notifikasi ke wali

## Fase 10 — Gamifikasi
Acuan: SPEC 3.20
- [ ] Aturan poin, transaksi poin idempoten, level, lencana, streak
- [ ] Leaderboard, peserta terbaik minggu ini, piagam apresiasi

## Fase 11 — Evaluasi, Pengumuman, Notifikasi, Sertifikat
Acuan: SPEC 3.21, 3.22, 3.25
- [ ] Form builder evaluasi + mode anonim + hasil
- [ ] Pengumuman bersasaran, pusat notifikasi, channel WhatsApp (interface + driver log)
- [ ] Word of the Day
- [ ] Sertifikat PDF + QR verifikasi publik

## Fase 12 — Dashboard
Acuan: SPEC 3.23, 3.24
- [ ] Dashboard peserta, pengajar, tim kerja, admin program
- [ ] Dashboard pimpinan lengkap dengan drill-down, peringatan dini, profil 360°, cache
- [ ] Ringkasan eksekutif PDF + email mingguan

## Fase 13 — Laporan
Acuan: SPEC 5
- [ ] 13 laporan Excel & PDF dengan kop resmi, font CJK untuk dompdf
- [ ] Ekspor sebagai Job + notifikasi file siap

## Fase 14 — Pengerasan & Kualitas
Acuan: SPEC 6, 7, 9
- [ ] Semua jadwal scheduler terdaftar dan teruji
- [ ] Retensi data, rate limit, signed URL, header keamanan
- [ ] Cakupan test sesuai SPEC 9 lengkap; uji performa dengan DemoSeeder
- [ ] PWA: cache materi, halaman offline, ikon install
- [ ] Uji menyeluruh di HP lewat `make tunnel`

## Fase 15 — Produksi
Acuan: DOCKER.md bagian C–D
- [ ] Target `prod` di Dockerfile, `compose.prod.yaml`, Caddyfile, `.env.production.example`, `scripts/deploy.sh`
- [ ] Uji mode produksi di localhost (`make prod-local`) lulus
- [ ] Backup harian + salinan offsite + uji pemulihan
- [ ] Panduan deploy VPS di `README.md` (langkah D.5–D.7)
- [ ] Deploy ke VPS dan verifikasi di HP

---

## Catatan Progres
(Tulis ringkasan tiap fase di sini setelah selesai.)

### Fase 0 — 24 Okt 2026
- Laravel 13 + Livewire 4 + Flux dari starter kit resmi (auth dipangkas: login + lupa sandi). Pest 4 dengan seed role otomatis.
- Docker: `docker/app/Dockerfile` (base/dev/vendor/assets/prod), `compose.yaml`, `Makefile`, `.dockerignore`, `scripts/copy-face-models.mjs`.
- Tema Tailwind v4 (kunyit, woka, woka-light, hutan, rica, santan), font Baloo 2 + Plus Jakarta Sans di-host lewat Vite.
- Layout: sidebar desktop + drawer HP, bottom nav untuk peserta/wali/tim kerja, komponen `x-card`, `x-stat`, `x-status-badge`, `x-empty-state`, `x-confirm-button`, `x-page-header`.
- Halaman login dengan logo Wokabulari + logo Poltekkes Kemenkes Manado & BP3MI (slot berkas `public/images/logos/bp3mi.png`).
- PWA: manifest, service worker (cache aset, materi, halaman offline).
- Bahasa Indonesia (`lang/id`), zona waktu WITA, helper `tanggal()`, `tanggal_panjang()`, `jam()`.
- Login No. HP/email/NIS-NIP, wajib ganti kata sandi saat login pertama, persetujuan data pribadi.
- Catatan: `make build/up/install` belum diuji karena Docker belum terpasang di mesin pengembang; test dijalankan dengan `php artisan test` (24 lulus). Tampilan login dicek di lebar 360px (terang & gelap).

### Fase 1 — 24 Okt 2026
- Role & permission granular (`config/wokabulari.php`), permission tim kerja per unit, super admin lewat `Gate::before`, pimpinan read-only.
- Login No. HP/email/NIS-NIP, `app:create-admin`, `app:import-participants`, wajib ganti sandi awal, persetujuan data pribadi.
- CRUD: angkatan, bahasa, lokasi (peta Leaflet/OSM + radius), kelas + anggota kelas, peserta (+ import Excel/CSV + template, reset sandi), pengguna & unit tim kerja, asrama + kamar + penempatan penghuni, vendor.
- Pengaturan sistem (tabel `settings` + cache), matriks role & hak akses, log audit (spatie/activitylog).
- Pendaftaran wajah referensi: kamera + liveness (kedip/menoleh) + 3 sampel descriptor → rata-rata terenkripsi di server.
- ProductionSeeder (role, bahasa) & DemoSeeder (akun SPEC 8, 4 kelas × 8 peserta, lokasi, 2 asrama, 2 vendor).
- Test: 64 lulus. Alur login pertama dicek di 360px.
- Belum: import Excel khusus penempatan kamar (penempatan masih manual per kamar).
