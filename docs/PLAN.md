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
- [x] Halaman kamera + liveness + pengambilan descriptor (face-api)
- [x] Pencocokan wajah & geofence di server, status hadir/terlambat/ditolak
- [x] Persetujuan manual oleh admin/pengajar
- [x] Riwayat absen peserta

## Fase 3 — Sesi, Kode Kehadiran, Materi, Izin
Acuan: SPEC 3.3, 3.4, 3.5, 3.11
- [x] Jadwal sesi (termasuk berulang), mulai/selesai sesi
- [x] Kode + QR, mode proyektor, monitor realtime, penandaan manual & otomatis
- [x] Input kode / pindai QR peserta + rate limit
- [x] Materi per sesi, bank materi, log akses, cache offline
- [x] Izin/sakit kelas + sinkron ke status kehadiran

## Fase 4 — Penilaian Sesi & Periodik
Acuan: SPEC 3.6, 3.9, 3.10
- [x] Komponen penilaian + template, input skor massal, nilai tertimbang
- [x] Catatan per sesi (opsi boleh dilihat wali)
- [x] Penilaian mingguan/bulanan dengan saran nilai, draft/terbit
- [x] Halaman nilai peserta + grafik

## Fase 5 — Tugas / PR
Acuan: SPEC 3.7
- [x] Buat tugas, lampiran, jenis pengumpulan
- [x] Pengumpulan dari HP (foto, file, audio), terlambat & revisi
- [x] Penilaian cepat + voice note, integrasi ke komponen sesi
- [x] Pengingat tenggat

## Fase 6 — Quiz, Pretest, Posttest, Tryout
Acuan: SPEC 3.8
- [x] Bank soal + import Excel, semua tipe soal termasuk hanzi/pinyin & nada
- [x] Pengerjaan quiz: timer, autosave, auto-submit, deteksi pindah tab
- [x] Penilaian otomatis & antrean "Perlu Dinilai"
- [x] N-Gain pretest–posttest, analisis butir soal, tryout format HSK
- [x] Test: kunci jawaban tidak bocor

## Fase 7 — Konsumsi & Rating
Acuan: SPEC 3.12, 3.13, 3.14
- [x] Jadwal makan, duplikasi mingguan, daftar alergi
- [x] Tanda tangan penerimaan + penerimaan diwakilkan, monitor realtime
- [x] Rating vendor & rating pengajar mingguan (anonim), pengingat Jumat

## Fase 8 — Asrama & Kesehatan
Acuan: SPEC 3.15–3.18
- [x] Absen asrama malam + konfirmasi/apel manual pengelola
- [x] Izin keluar asrama + QR keluar/masuk + `late_return`
- [x] Keluhan & inventaris kamar
- [x] Laporan sakit + tombol darurat + timeline penanganan + integrasi (izin sakit kelas, makanan khusus, status asrama)

## Fase 9 — Portal Wali
Acuan: SPEC 3.19
- [x] Undangan & verifikasi wali, login
- [x] Tampilan anak (kehadiran, nilai, tugas, izin, sakit, poin)
- [x] Persetujuan wali untuk izin keluar
- [x] Notifikasi ke wali

## Fase 10 — Gamifikasi
Acuan: SPEC 3.20
- [x] Aturan poin, transaksi poin idempoten, level, lencana, streak
- [x] Leaderboard, peserta terbaik minggu ini, piagam apresiasi

## Fase 11 — Evaluasi, Pengumuman, Notifikasi, Sertifikat
Acuan: SPEC 3.21, 3.22, 3.25
- [x] Form builder evaluasi + mode anonim + hasil (survei sarana & prasarana; syarat unduh sertifikat menyusul bersama modul sertifikat)
- [x] Pengumuman bersasaran, pusat notifikasi, channel WhatsApp (interface + driver log)
- [x] Word of the Day (Kosakata hari ini: 5 kartu per hari, v1.8.0)
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

### Fase 2 — 25 Okt 2026
- Absen harian: kamera depan + liveness acak (kedip / menoleh kiri / kanan), descriptor face-api, selfie JPEG terkompresi ke disk privat, GPS akurasi tinggi.
- Server: jendela buka absen, akurasi GPS maks., geofence Haversine terhadap lokasi kelas, pencocokan wajah Euclidean vs referensi terenkripsi, status hadir/terlambat/ditolak + alasan; percobaan ulang memperbarui baris yang sama (unik per hari).
- Kelola absen (pengajar/admin): ringkasan, filter belum absen/ditolak/terlambat, lihat selfie lewat signed URL, tandai manual dengan alasan (tercatat di log audit).
- Riwayat absen bulanan peserta dengan persentase kehadiran.
- Event `DailyAttendanceRecorded` (dipakai gamifikasi).
- Test: 76 lulus.

### Fase 3 — 25 Okt 2026
- Sesi (tunggal & berulang), mulai/selesai sesi, kode 6 karakter tanpa karakter ambigu + QR, perpanjang/ganti/tutup kode, mode proyektor, monitor polling 5 detik, penandaan manual & alpa otomatis saat sesi selesai (kecuali izin disetujui), scheduler `app:close-expired-codes` tiap menit.
- Input kode / pindai QR (html5-qrcode) dengan rate limit 5×/menit dan log percobaan gagal.
- Materi per sesi + bank materi (salin ke sesi), pratinjau PDF/audio/video/YouTube, unduh lewat signed URL, log akses, cache offline service worker.
- Izin/sakit kelas dengan lampiran; persetujuan mengisi status di absen harian & sesi; notifikasi (database/email/WhatsApp driver `log`).
- Cast `DateOnly` untuk semua kolom DATE. Test: 96 lulus.

### Fase 4 — 26 Okt 2026
- Komponen penilaian per sesi (aspek, bobot, skor maks.) + template milik pengajar; komponen dari quiz/tugas (source quiz/assignment) diisi otomatis & terkunci.
- Tabel skor peserta × komponen: simpan otomatis per sel tanpa render ulang, navigasi Enter/↑/↓, nilai akhir tertimbang 0–100 diperbarui langsung.
- Catatan perkembangan per sesi (aspek, boleh dilihat wali, status dibaca).
- Penilaian mingguan/bulanan: kartu per peserta (nyaman di HP), saran nilai dari komponen sesi pada periode itu, rata-rata & predikat otomatis, draf → terbit + notifikasi, kembalikan ke draf.
- Halaman Nilai & Masukan peserta: penilaian terakhir, grafik radar & garis per aspek (Chart.js), timeline catatan + penilaian dengan badge baru, nilai per sesi.
- Test: 115 lulus. Dicek di 360px: panel penilaian, autosave sel, penilaian periodik, halaman nilai peserta.

### Fase 5 — 26 Okt 2026
- Tugas per kelas (opsional ditautkan ke sesi): instruksi, lampiran, jenis pengumpulan (teks/berkas/foto/audio/campuran), aspek, tenggat, skor maks., izinkan terlambat + potongan %, izinkan kumpul ulang; draf → terbit + notifikasi.
- Peserta: tab Belum dikerjakan / Mendekati tenggat (<48 jam) / Selesai; kumpul dari HP (foto kamera dikompres, berkas, rekaman suara MediaRecorder), status terlambat, revisi.
- Pengajar: mode penilaian cepat (geser/prev-next), nilai akhir otomatis dipotong bila terlambat, komentar teks + voice note, minta revisi.
- Nilai tugas yang ditautkan sesi masuk komponen sesi (skala 0–100, terkunci); tugas tanpa sesi ikut saran penilaian periodik.
- Pengingat H-1 (18.00) dan 2 jam sebelum tenggat (tiap 30 menit) hanya ke yang belum mengumpulkan, tanpa dobel.
- Test: 125 lulus. Dicek di 360px: daftar & detail tugas peserta.

### Fase 6 — 26 Okt 2026
- Bank soal per bahasa/level/topik (dipakai ulang lintas quiz) + import Excel dengan template; 8 tipe soal: pilihan ganda, benar/salah, isian singkat (beberapa jawaban diterima, normalisasi huruf & tanda baca), menjodohkan (nilai parsial), esai, menyimak (audio), berbicara (rekam suara), nada Mandarin 1–4.
- Hanzi+pinyin / kana+romaji dengan tampilan bacaan per quiz: `<ruby>`, disembunyikan, atau ketuk; font Noto Sans SC/JP hanya dimuat di halaman CJK.
- Quiz/pretest/posttest/tryout: jadwal buka–tutup, durasi, jumlah percobaan, nilai lulus, acak soal & opsi, waktu tampil hasil (langsung/setelah tutup/manual), tautan ke sesi (nilai masuk komponen), skala skor tryout (HSK 200) + bagian soal + target peserta.
- Pengerjaan: satu soal per layar, timer dari server, autosave 10 detik, auto-kumpul saat habis (browser + scheduler tiap menit), deteksi pindah tab, satu attempt aktif & satu sesi (sesi baru mengambil alih), validasi waktu di server.
- Kunci jawaban tidak pernah dikirim ke browser sebelum dikumpulkan (diuji pada HTML & snapshot Livewire).
- Antrean "Perlu Dinilai" untuk esai/berbicara; analisis butir (kesukaran & daya pembeda 27%); N-Gain per peserta & kelas dengan kategori.
- Halaman nilai peserta: pretest vs posttest + riwayat quiz.
- Perbaikan: `x-confirm-button` kini memanggil aksi tanpa argumen (mis. Selesai sesi, Terbitkan).
- Test: 136 lulus. Dicek di 360px: daftar quiz, pengerjaan tryout (timer, autosave), halaman hasil.

### Fase 7 — 25 Okt 2026
- Jadwal makan per minggu (sarapan/siang/malam): vendor, menu, rentang pengambilan, porsi; isi cepat dari slot kosong; salin ke minggu depan tanpa menimpa.
- Halaman Konsumsi peserta: jadwal yang sedang dibuka → tanda tangan di signature pad (PNG di disk privat), sekali per jadwal dan hanya dalam rentang waktu; status hari ini & riwayat; pengingat alergi pribadi.
- Monitor makan (polling 5 detik): diterima vs total peserta, daftar belum ambil dengan penanda izin/sakit & alergi, penerimaan diwakilkan (tanda tangan petugas + catatan), daftar makanan khusus (status disiapkan/diantar), tautan tanda tangan bertanda tangan (signed URL).
- Daftar alergi & pantangan per asrama + salin teks untuk vendor.
- Rating mingguan: vendor (ikon hati) dan pengajar (bintang) dengan sub-aspek, komentar, saran, opsi anonim; sekali per minggu; pengingat Jumat 15.00 (`app:rating-reminders`) hanya ke yang belum mengisi.
- Rekap: rating vendor (unit konsumsi & pimpinan), rating pengajar dengan nama (admin & pimpinan), "Rating saya" untuk pengajar tanpa nama penilai anonim; tren 8 minggu.
- Test: 160 lulus. Dicek di 360px: monitor (termasuk modal diwakilkan + tanda tangan), jadwal, alergi, rekap rating, konsumsi & rating peserta.

### Fase 8 — 25 Okt 2026
- Absen asrama malam: selfie + verifikasi wajah + geofence asrama dalam jendela waktu per asrama → menunggu konfirmasi; pengelola mengonfirmasi per orang/per kamar, apel manual, tandai tidak di asrama (polling 15 detik, merah setelah jam malam).
- Izin keluar: sementara/pulang/menginap/darurat dengan batas per minggu, minimal jam sebelum keluar, cek bentrok sesi; persetujuan wali untuk jenis tertentu (bila wali terverifikasi) lalu pengelola; QR izin untuk pindai keluar & kembali; `app:check-late-returns` tiap 15 menit → `late_return` + notifikasi pengelola & wali.
- Keluhan & permintaan asrama dengan foto, prioritas, status hingga selesai; inventaris barang per kamar.
- Laporan sakit oleh peserta/teman sekamar/petugas: gejala, keparahan, suhu, foto; tombol DARURAT (konfirmasi, log setiap tekan, rate limit notifikasi); timeline penanganan (obat, rujukan, pemantauan + pengingat tiap jam); integrasi otomatis izin sakit kelas (dikonfirmasi petugas), absen asrama `sick_in_room`, permintaan makanan khusus ke unit konsumsi; penanda izin/sakit di monitor makan.
- Privasi: deskripsi & catatan kondisi terenkripsi dan tersembunyi dari serialisasi; detail hanya peserta ybs., petugas asrama/kesehatan, super admin, wali terverifikasi; pimpinan ditolak.
- Scheduler: pengingat belum absen asrama ke pengelola (tiap 15 menit, hanya bila daftar berubah) & ke wali setelah jam malam (sekali per malam), tutup absen malam 00.05.
- Perbaikan: varian mode gelap untuk teks redup di semua halaman.
- Test: 177 lulus. Dicek di 360px: absen asrama, izin keluar, lapor sakit (peserta); absen malam, izin keluar, pindai QR, keluhan, inventaris, laporan sakit & detail (petugas).

### Fase 9 — 25 Okt 2026
- Undangan wali oleh admin (kode + tautan, dikirim via WhatsApp/email bila diisi) atau kode yang dibagikan peserta dari menu Wali Saya; halaman publik Daftar akun wali (tautan di halaman login) dengan rate limit; wali yang sudah punya akun menautkan anak lain lewat Hubungkan Anak.
- Verifikasi hubungan oleh admin program di menu Wali (menunggu/terverifikasi/undangan, batalkan undangan); undangan admin + nomor HP sama langsung terverifikasi.
- Portal wali (mobile): beranda kartu anak + status hari ini, Ringkasan Anak (kelas/asrama hari ini, kehadiran 30 hari, tugas terkumpul, izin keluar, laporan sakit, kontak asrama), kalender kehadiran kelas & asrama berwarna, nilai periodik & catatan yang boleh dilihat wali + pretest–posttest, persetujuan izin keluar. Pemilih anak bila lebih dari satu.
- Privasi: wali hanya melihat anak terverifikasi (selain itu 403), tanpa selfie, data peserta lain, atau rating anonim.
- Notifikasi ke wali: anak alpa di sesi, belum absen asrama setelah jam malam, izin keluar (perlu persetujuan, disetujui/ditolak, terlambat, sudah kembali), laporan sakit, nilai bulanan terbit.
- Test: 188 lulus. Dicek di 360px: beranda wali, ringkasan anak, kehadiran, nilai, persetujuan, daftar wali, halaman admin Wali (termasuk buat undangan).

### Fase 10 — 25 Okt 2026
- Aturan poin (`gamification_rules`) dapat diatur admin: poin, batas per hari, aktif/nonaktif; pengurang poin alpa & terlambat kembali nonaktif bawaan.
- `point_transactions` idempoten (unique user+event+referensi) lewat Event & Listener: absen harian tepat waktu, input kode sesi, absen asrama mandiri terkonfirmasi, tugas tepat waktu, nilai tugas ≥ 85, quiz (skor% ÷ 5 maks. 20 + bonus sempurna), rating mingguan; penyesuaian manual admin tercatat.
- Level bumbu woku (Kemangi → Serai → Jahe → Kunyit → Woku Komplit) + notifikasi naik level; 10 lencana berbasis kriteria JSON (poin, streak, jumlah event, aspek penilaian, juara mingguan) + notifikasi.
- Streak harian (hari tanpa sesi tidak memutus) dengan animasi api di beranda; `app:update-streaks` 00.05.
- Peringkat mingguan/minggu lalu/total per kelas & antarkelas (rata-rata), nama tersembunyi tampil inisial; Peserta Terbaik Minggu Ini di beranda; piagam apresiasi PDF; lencana Bintang Pekan Ini tiap Senin 06.00.
- Halaman Poin & Lencana (riwayat transparan, pilihan sembunyikan nama), admin Gamifikasi, kartu level & lencana anak di portal wali.
- `app:gamification-backfill` untuk menghitung dari data lama (idempoten).
- Test: 202 lulus. Dicek di 360px: beranda peserta, poin & lencana, peringkat, admin gamifikasi, portal wali; piagam PDF dicek.

### Fase 11 (sebagian) — 25 Okt 2026
- Pengumuman bersasaran (semua, kelas, asrama, peran) dengan sumber yang dibedakan: Tim Kerja (toska), Pengajar (limau), Asrama (ungu) — ikon, garis warna, filter per sumber, jumlah belum dibaca per sumber.
- Kelola pengumuman: pengajar hanya ke kelasnya, unit asrama ke asrama, admin program & tim kerja ke semua sasaran; tandai penting, kirim juga via WhatsApp, terbit sekarang/terjadwal/draf; notifikasi sekali ke sasaran (termasuk wali dari kelas/asrama sasaran).
- Tanda sudah dibaca (buka dari notifikasi langsung terbuka & tertandai), "Tandai semua dibaca", kartu Pengumuman terbaru di beranda semua peran.
- Pusat notifikasi & channel WhatsApp (interface + driver log) sudah tersedia sejak fase sebelumnya.
- Palet warna mengikuti identitas Kemenkes & logo BP3MI resmi di halaman login.
- Test: 210 lulus. Dicek di 360px: daftar pengumuman (filter, buka, tanda dibaca), form pengajar, beranda.

### Fase 11 (sebagian) — 30 Okt 2026
- Menu **Survei & Evaluasi** untuk tim kerja: form builder (skala 1–5, pilihan ganda, isian) dengan templat *Sarana & prasarana* dan *Evaluasi program*, aspek per pertanyaan, pertanyaan khusus peserta/pengajar, mode anonim (hash HMAC), jadwal buka & tenggat.
- Sasaran: satu atau beberapa **angkatan**, untuk **peserta** dan/atau **tim pengajar** angkatan tersebut. Pemberitahuan saat terbit/dibuka dan pengingat menjelang tenggat (`app:evaluation-notifications`, tiap 15 menit).
- Peserta & pengajar: halaman Survei (perlu diisi / riwayat), pengisian sekali per survei, pengingat di beranda; peserta mendapat +20 poin.
- Hasil: partisipasi per kelompok, Indeks Kepuasan 0–100, rata-rata per aspek (terendah di atas), sebaran jawaban, daftar saran; dapat dilihat pimpinan (read-only). Sasaran & pertanyaan terkunci setelah ada jawaban.
- Kelas bisa punya beberapa pengajar (tim pengajar) dengan akses setara; pengajar utama tetap tercatat.
- Test: 264 lulus. Dicek di 360px: daftar & pengisian survei, editor survei, hasil.

### Fase 12 & 13 (sebagian) + permintaan tambahan — 31 Okt 2026 (v1.7.0)
- Input konsumsi pindah ke **tim konsumsi** (vendor dipilih per input); snack hanya ditambah bila tersedia; role vendor dicabut (akun lama dinonaktifkan).
- **Master Komponen Poin**: aturan otomatis per kategori + komponen tambahan; pengajuan komponen oleh pengajar (setujui/tolak) dan pemberian poin ke peserta kelas; poin baru *Isi tanda terima konsumsi*.
- **Panduan Penggunaan** per role.
- **Dashboard Eksekutif** (pimpinan & admin program, cache 5 menit): angka utama, catatan keputusan, tren & sebaran kehadiran, perbandingan antarkelas, tren/sumber nilai, predikat, konsumsi (dipesan/diterima/tanda terima, serapan per jenis), perlu perhatian, drill-down per peserta. Belum: N-Gain pretest–posttest, kinerja pengajar, ringkasan eksekutif PDF & email mingguan.
- **Pusat Laporan**: absen harian, absen per sesi, konsumsi diterima, input konsumsi, rekap nilai (harian/mingguan/bulanan, filter angkatan & kelas) dengan unduh Excel. Belum: PDF berkop, ekspor sebagai Job, laporan asrama/izin/rating/keluhan/kesehatan/evaluasi/poin/akses materi.
- **Biodata peserta** di menu profil dengan persentase kelengkapan dan pengingat di beranda.
- Test: 296 lulus. Dicek di 360px: input konsumsi, komponen poin, poin peserta, panduan, dashboard eksekutif (filter, grafik, drill-down), pusat laporan, biodata.

### Tambahan — 31 Okt 2026 (v1.8.0)
- Kartu **bagikan lencana** (Story 9:16 / Kotak 1:1) dibuat di HP, bagikan langsung atau unduh; notifikasi lencana membuka kartu.
- **Kosakata hari ini** di beranda peserta (5 kartu geser, ketuk untuk arti, tombol dengar), bank awal Mandarin 122 kata; halaman **Bank Kosakata** untuk pengajar & admin program.
- Test: 300 lulus. Dicek di 360px: beranda peserta (kartu kosakata, geser & balik kartu), modal bagikan lencana (kedua format), bank kosakata.

### Tambahan — 31 Okt 2026 (v1.9.0)
- **Import Bank Kosakata** (Excel/CSV) dengan templat per bahasa & lembar petunjuk; kata yang ada diperbarui, baris salah dilaporkan.
- Test: 303 lulus. Dicek di 360px: Bank Kosakata dan modal import.

### Tambahan — 2 Nov 2026 (v1.10.0)
- Tim pengajar/tim kerja yang ditandai **Menerima konsumsi** menandatangani penerimaan seperti peserta (menu Konsumsi Saya, monitor, porsi, notifikasi, laporan).
- **Kehadiran mengajar** otomatis (kode sesi + ada peserta) dan **Laporan Mengajar** per pengajar di Pusat Laporan & halaman pengajar, unduh Excel.
- Test: 311 lulus. Dicek di 360px: Laporan Mengajar (pengajar), Konsumsi Saya.

### Tambahan — 2 Nov 2026 (v1.11.0)
- Tombol **Konfirmasi otomatis absen asrama** di Pengaturan Sistem; mati = pengelola asrama mengonfirmasi manual.
- Test: 314 lulus. Dicek di 360px: Pengaturan Sistem (kartu Asrama).

### Tambahan — 2 Nov 2026 (v1.12.0)
- Admin program ikut menyetujui izin keluar asrama.
- Pengaturan Sistem: panel **Penyimpanan & cadangan** (disk VPS, pemakaian Wokabulari) dan tombol **Buat cadangan sekarang** dengan daftar unduhan.
- Test: 319 lulus. Dicek di 360px: Pengaturan Sistem (panel penyimpanan, cadangan dibuat & diunduh di lokal).

### Tambahan — 3 Nov 2026 (v1.13.0)
- Absen harian & asrama terlambat tetap bisa, tercatat Terlambat, dan mengurangi poin (aturan pelanggaran di Komponen Poin); batas absen asrama terlambat di Pengaturan Sistem.
- Poin & Lencana: total pengurangan minggu ini dan filter Penambahan/Pengurangan.
- Test: 324 lulus. Dicek di 360px: Poin & Lencana (filter pengurangan).

### Tambahan — 3 Nov 2026 (v1.14.0)
- Dashboard eksekutif bertab (Ringkasan, Kehadiran, Nilai, Konsumsi, Peserta) dan tabel peserta yang bisa diurutkan per kolom & difilter.
- Test: 325 lulus. Dicek di 360px: tab Peserta (urut & filter), tab aktif bergeser ke tampilan.

### Tambahan — 30 Okt 2026 (v1.15.0)
- Penilaian periodik 4 langkah dengan filter peserta (cari, Belum dinilai/Draf/Terbit), kemajuan penilaian, dan bilah simpan/terbitkan yang selalu terlihat.
- Master Data → Aspek Penilaian; pengajar memilih aspek per kelas (Atur aspek). Nilai lama dipindah ke tabel `assessment_scores`.
- Dokumentasi kelas wajib (foto kamera/unggah file) saat mulai & akhir sesi mulai 31 Okt 2026; pengaturan di Pengaturan Sistem.
- Panel Sesi berjalan di dashboard eksekutif (diperbarui tiap menit).
- Test: 340 lulus. Dicek di 360px: Penilaian periodik (langkah, filter, Atur aspek), Master Aspek Penilaian, detail sesi (dialog dokumentasi, mulai sesi dengan foto), dashboard eksekutif (Sesi berjalan).
