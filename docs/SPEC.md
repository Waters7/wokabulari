# SPESIFIKASI APLIKASI WOKABULARI

Kelas Bahasa SMK Go Global – Poltekkes Kemenkes Manado
Versi spesifikasi: 1.0 (24 Oktober 2026)

Dokumen ini adalah sumber kebenaran fitur. Urutan pengerjaan ada di `docs/PLAN.md`; infrastruktur di `docs/DOCKER.md`.

---

## 1. Identitas & Desain

### 1.1 Identitas
- **Nama:** WOKABULARI — plesetan dari *woku* (masakan khas Manado) dan *vocabulary*. Bukan singkatan. "Woka" juga nama daun pembungkus woku.
- **Tagline:** "Kaya Kosakata, Torang Go Global!"
- **Subjudul:** Kelas Bahasa SMK Go Global · Poltekkes Kemenkes Manado
- **Logo:** blanga (wadah masak) berisi woku; uapnya membentuk balon percakapan berisi huruf **A · 中 · あ · Ä** (Inggris, Mandarin, Jepang, Jerman); hiasan cabai rica dan daun kemangi. Tulisan "Woka" hijau + "bulari" hijau tua. File di `docs/brand/`:
  - Logo: `logo-horizontal.svg/.png`, `logo-horizontal-dark.svg/.png` (latar gelap), `logo-mono.svg/.png` (satu warna untuk kop surat & stempel), `logo-mark.svg`, `logo-mark-dark.svg`
  - Ikon: `icon.svg`, `favicon.svg`, `icon-512.png`, `icon-192.png`, `icon-96.png`, `favicon-48.png`, `apple-touch-icon-180.png`, `icon-maskable-512.png`
  - Semua huruf di file logo sudah berupa path, jadi tidak bergantung font. Ikon ukuran kecil memakai tiga titik pengganti huruf.
- Gunakan nama WOKABULARI di judul halaman, manifest PWA, kop laporan PDF, sertifikat, email, dan notifikasi.

### 1.2 Palet (daftarkan sebagai theme Tailwind v4 lewat `@theme`)
| Token | Hex | Pemakaian |
|---|---|---|
| `kunyit` | `#F2A81D` | latar ikon, sorotan, lencana, progres |
| `woka` | `#2E7D4F` | warna utama: tombol, tautan, judul |
| `woka-light` | `#5DB57B` | aksen sekunder, grafik |
| `hutan` | `#123B2D` | teks utama, latar gelap, header |
| `rica` | `#C8312A` | aksen, tagline, peringatan, tombol darurat |
| `santan` | `#FFFBF2` | latar terang aplikasi |

Pastikan kontras teks minimal 4.5:1 (jangan memakai teks kuning di atas latar terang).

### 1.3 Huruf (Google Fonts, self-host lewat Vite bila memungkinkan)
- **Baloo 2** (700/800): logo dan judul
- **Plus Jakarta Sans** (500/600/700): teks aplikasi
- **Noto Sans SC** dan **Noto Sans JP**: hanya dimuat di halaman yang menampilkan teks Mandarin/Jepang

### 1.4 Pola UI
- Mobile-first, diuji di lebar 360px. Bottom navigation (maks. 5 item) untuk peserta, wali, dan tim kerja di HP; sidebar untuk admin, pengajar, dan pimpinan di desktop (berubah menjadi drawer di HP).
- Komponen: kartu statistik, badge status berwarna, toast, modal konfirmasi, skeleton loading, empty state dengan ilustrasi sederhana, dark mode opsional.
- Target sentuh minimal 44px; form satu kolom di HP; tabel lebar diberi scroll horizontal di dalam kotak.
- Sapaan bernuansa Manado di beranda dan pesan sukses, teks lain tetap Bahasa Indonesia baku. Contoh: "Selamat pagi! Ngana so siap blajar?", "Mantap, ngana nyanda absen 7 hari!", "Ayo jo, kumpul tugas sebelum tenggat!"
- **PWA:** manifest (nama, ikon, warna `#2E7D4F`, latar `#FFFBF2`), service worker untuk cache aset statis, halaman offline, dan materi yang sudah dibuka.

---

## 2. Role & Hak Akses

| Role (slug) | Nama tampilan | Ringkasan |
|---|---|---|
| `super_admin` | Super Admin | Semua akses, kelola pengguna, role, pengaturan sistem |
| `leader` | Pimpinan | **Read-only**: dashboard pemantauan & semua laporan (tanpa detail medis) |
| `program_admin` | Admin Program | Angkatan, kelas, peserta, pengajar, jadwal, pengumuman, evaluasi program, sertifikat |
| `teacher` | Pengajar | Sesi, kode kehadiran, materi, komponen nilai, tugas, quiz, penilaian, catatan |
| `staff` | Tim Kerja | Dibagi per unit (`staff_units`): **consumption** (konsumsi), **dormitory** (pengelola asrama), **health** (kesehatan, opsional). Satu orang boleh lebih dari satu unit |
| `participant` | Peserta | Absen, kode sesi, materi, tugas, quiz, konsumsi, asrama, rating, evaluasi |
| `guardian` | Orang Tua/Wali | Read-only data anak yang terhubung dan terverifikasi |

Vendor makanan **bukan** pengguna, hanya data master milik unit konsumsi.

Gunakan permission granular (contoh: `attendance.confirm-dorm`, `meal.manage`, `health.view-detail`, `report.export`) yang di-assign ke role, supaya hak akses bisa diatur tanpa ubah kode.

---

## 3. Modul & Fitur

### 3.1 Master Data & Pengaturan
- **Angkatan (batch):** nama, tanggal mulai–selesai, target kehadiran (default 80%), nilai minimal lulus (default 70).
- **Bahasa:** Inggris (`en`), Mandarin (`zh`), Jepang (`ja`), Jerman (`de`); tabel sendiri agar bahasa baru mudah ditambah. Simpan format ujian acuan (mis. IELTS/TOEIC, HSK, JFT/JLPT, Goethe).
- **Kelas (classroom):** angkatan, bahasa, nama, level, pengajar utama, lokasi.
- **Peserta:** import dari Excel (template tersedia untuk diunduh), profil lengkap, foto wajah referensi (diambil lewat kamera saat onboarding, minimal 3 sampel → descriptor rata-rata).
- **Lokasi:** kelas dan asrama, dengan latitude, longitude, radius (meter). Pilih titik di peta (Leaflet + OpenStreetMap).
- **Asrama & kamar:** asrama putra/putri, jam malam, kamar, kapasitas, penempatan penghuni (import Excel).
- **Vendor makanan.**
- **Pengaturan sistem** (tabel `settings`, dapat diubah super admin): radius default, ambang kemiripan wajah (default 0.5), durasi kode sesi (default 15 menit), jam batas terlambat kelas, jendela absen asrama, jam malam, aturan izin keluar (maks. per minggu, minimal jam pengajuan sebelumnya, wajib persetujuan wali untuk jenis tertentu), aturan poin gamifikasi, batas ukuran file.
- **Persetujuan data pribadi:** saat login pertama, peserta dan wali wajib menyetujui pemrosesan data wajah, lokasi, dan kesehatan (sesuai UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi). Simpan versi teks persetujuan dan waktunya.

### 3.2 Absen Harian Peserta (Wajah + Lokasi)
1. Peserta membuka menu Absen → kamera depan aktif (`getUserMedia`).
2. **Liveness check** sederhana: minta peserta berkedip atau menoleh (deteksi landmark face-api).
3. Ambil selfie, hitung descriptor di browser, kirim ke server bersama koordinat GPS (`enableHighAccuracy`) dan akurasinya.
4. **Server** menghitung jarak Euclidean descriptor terhadap referensi (descriptor referensi tidak pernah dikirim ke browser) dan jarak ke lokasi kelas dengan rumus Haversine. Tolak jika di luar radius atau akurasi GPS terlalu buruk (> 100 m, dapat diatur).
5. Simpan selfie (dikompres, disk privat), skor wajah, koordinat, jarak, waktu, info perangkat. Status `present` atau `late` (lewat jam batas) atau `rejected` (dengan alasan).
6. Satu kali per hari (unique `user_id + date`). Jika gagal, peserta bisa mencoba lagi; admin/pengajar bisa menyetujui manual dengan alasan (tercatat di log).

### 3.3 Sesi Belajar & Kode Kehadiran
- Pengajar membuat sesi: kelas, tanggal, jam mulai–selesai, topik, tujuan pembelajaran. Bisa membuat jadwal berulang (mis. Senin–Jumat selama 4 minggu).
- Tombol **Mulai Sesi** → kode 6 karakter acak tanpa karakter ambigu (tanpa O/0, I/1/L) + QR code.
- Kode berlaku sesuai pengaturan (default 15 menit), bisa diperpanjang, di-regenerate (kode lama nonaktif), atau ditutup manual. Ditutup otomatis oleh scheduler saat kedaluwarsa.
- **Mode proyektor:** layar penuh berisi kode besar, QR, dan hitung mundur.
- **Monitor realtime** (Livewire polling 5 detik): daftar peserta sudah/belum input kode.
- Pengajar dapat menandai manual `excused`/`sick`/`absent` dengan alasan.
- Tombol **Selesai Sesi**: peserta yang belum input ditandai `absent` otomatis kecuali punya izin/sakit yang disetujui.

### 3.4 Input Kode Sesi (Peserta)
- Ketik kode atau pindai QR (html5-qrcode).
- Validasi server: kode aktif & belum kedaluwarsa, peserta terdaftar di kelas tersebut, **sudah absen harian hari ini**, belum pernah submit di sesi ini. Opsional: validasi lokasi.
- Rate limit 5 percobaan per menit per pengguna. Percobaan gagal dicatat.

### 3.5 Materi per Sesi
- Pengajar mengunggah beberapa materi per sesi: PDF, PPT/PPTX, DOC/DOCX, gambar, audio MP3/M4A, video MP4, atau tautan (YouTube/Google Drive).
- Batas ukuran default 50 MB per file (dapat diatur); gambar dikompres otomatis.
- Visibilitas: langsung, terjadwal (`visible_from`), atau setelah sesi selesai; opsi boleh/tidak diunduh.
- Peserta: pratinjau langsung (PDF viewer, audio/video player) dan unduh lewat signed URL. Materi yang sudah dibuka di-cache service worker untuk akses offline.
- Pengajar melihat siapa saja yang sudah membuka/mengunduh.
- **Bank materi:** cari/filter per bahasa, level, topik; salin materi ke sesi lain.
- Notifikasi ke peserta saat materi baru tersedia.

### 3.6 Komponen Penilaian per Sesi
- Pengajar menentukan komponen yang dinilai pada sesi: nama, aspek bahasa (`listening`, `speaking`, `reading`, `writing`, `vocabulary`, `grammar`, `attitude`, `participation`), bobot (%), skor maksimal. Contoh: Speaking Practice, Role Play, Partisipasi, Tugas.
- **Template komponen** milik pengajar yang bisa dipakai ulang.
- Input skor massal dalam tabel (baris peserta × kolom komponen), simpan otomatis per sel (Livewire, debounce), navigasi keyboard.
- Nilai akhir sesi = rata-rata tertimbang (dinormalisasi ke 0–100).
- Skor **quiz** dan **tugas** yang ditautkan ke sesi otomatis menjadi komponen (sumber `quiz`/`assignment`, tidak bisa diedit manual).

### 3.7 Tugas / PR Harian
- Pengajar membuat tugas (opsional ditautkan ke sesi): judul, instruksi, lampiran, jenis pengumpulan (`text`, `file`, `photo`, `audio`, `mixed`), tenggat, skor maksimal, izinkan terlambat + potongan nilai (%), izinkan kumpul ulang.
- Peserta melihat tab: Belum dikerjakan, Mendekati tenggat, Selesai. Mengumpulkan dari HP: kamera untuk foto tugas tulis, perekam suara untuk tugas speaking.
- Pengajar menilai dalam tampilan cepat (geser antarpeserta): nilai, komentar teks atau **voice note**, atau minta revisi.
- Status: `not_submitted`, `submitted`, `late`, `graded`, `revision_requested`.
- Pengingat H-1 dan 2 jam sebelum tenggat; notifikasi saat dinilai.
- Nilai tugas dapat masuk komponen sesi dan saran penilaian mingguan.

### 3.8 Quiz, Pretest, Posttest
- **Bank soal** per bahasa/level/topik, dapat dipakai ulang lintas quiz dan angkatan. Import soal dari Excel (template tersedia).
- Tipe soal: `multiple_choice`, `true_false`, `short_answer`, `matching`, `essay`, `listening` (audio), `speaking` (peserta merekam suara), `tone` (khusus Mandarin, pilih nada 1–4).
- Teks soal mendukung hanzi + pinyin dan kana/kanji + romaji. Opsi tampilan bacaan: di atas teks (`<ruby>`), disembunyikan, atau muncul saat diketuk.
- Pengaturan quiz: jenis (`quiz`, `pretest`, `posttest`, `tryout`), jadwal buka–tutup, durasi, jumlah percobaan, nilai lulus, acak soal & opsi, waktu tampil hasil (`immediately`, `after_close`, `manual`).
- **Tryout** mengikuti format ujian acuan bahasa (mis. HSK 1–4 untuk Mandarin) dengan target skor per peserta.
- Penilaian otomatis untuk soal objektif; `essay`/`speaking` masuk antrean "Perlu Dinilai".
- Antikecurangan ringan: catat pindah tab, simpan jawaban otomatis tiap 10 detik, auto-submit saat waktu habis (juga oleh scheduler untuk attempt yang ditinggalkan), satu attempt aktif per peserta.
- Kunci jawaban **tidak pernah** dikirim ke browser sebelum attempt dikumpulkan.
- **Pretest vs posttest:** N-Gain = (post − pre) / (100 − pre); kategori tinggi (≥ 0.7), sedang (0.3–0.7), rendah (< 0.3). Ditampilkan per peserta dan per kelas.
- **Analisis butir soal:** tingkat kesukaran (persentase benar) dan daya pembeda sederhana.

### 3.9 Penilaian Mingguan/Bulanan & Catatan Perkembangan
- **Catatan per sesi** untuk peserta tertentu (opsional): aspek, isi catatan, opsi "boleh dilihat wali".
- **Penilaian mingguan & bulanan:** nilai 0–100 untuk listening, speaking, reading, writing, vocabulary, grammar, attitude; rata-rata dan predikat otomatis (A ≥ 85, B ≥ 75, C ≥ 65, D < 65; dapat diatur); feedback tertulis.
- Sistem menyarankan nilai awal dari rata-rata komponen sesi, quiz, dan tugas pada periode tersebut; pengajar dapat menyesuaikan.
- Input massal per kelas. Status `draft`/`published`; peserta dan wali hanya melihat yang terbit. Notifikasi saat terbit.

### 3.10 Halaman Nilai & Masukan Peserta
- Timeline catatan per sesi dan penilaian periodik, badge "Ada catatan baru" untuk yang belum dibaca.
- Grafik perkembangan per aspek (line & radar chart), perbandingan pretest–posttest, riwayat skor quiz dan tugas.

### 3.11 Izin / Sakit Kelas
- Peserta mengajukan izin atau sakit (rentang tanggal, alasan, lampiran surat). Disetujui pengajar atau admin program.
- Pengajuan yang disetujui otomatis mengisi status `excused`/`sick` pada absen harian dan sesi di rentang tersebut.
- Dapat dibuat otomatis dari Laporan Sakit Asrama (3.18).

### 3.12 Konsumsi 3x Sehari (Unit Konsumsi)
- Atur jadwal makan per hari (`breakfast`, `lunch`, `dinner`): vendor, menu, rentang waktu pengambilan, jumlah porsi dipesan. Duplikasi jadwal satu minggu ke minggu berikutnya.
- Peserta membuka menu Konsumsi → jadwal yang sedang aktif → **tanda tangan wajib** di signature pad → simpan PNG (disk privat). Hanya dalam rentang waktu dan sekali per jadwal.
- Penerimaan diwakilkan: petugas dapat mencatat penerimaan atas nama peserta yang sakit (dengan tanda tangan petugas dan catatan).
- Monitor realtime: porsi diterima vs total peserta, daftar yang belum mengambil.
- Daftar peserta dengan alergi/pantangan makanan untuk diteruskan ke vendor.
- Peserta yang sedang izin keluar/pulang atau sakit ditandai agar porsi dapat disesuaikan; permintaan makanan khusus dari laporan sakit tampil di sini.

### 3.13 Rating Vendor Mingguan (Peserta)
- Sekali per minggu per vendor: rating 1–5 berupa ikon hati/bintang (komponen Alpine.js), sub-aspek rasa, porsi, kebersihan, ketepatan waktu, komentar, saran.
- Pengingat otomatis setiap Jumat bila belum mengisi. Rekap untuk unit konsumsi dan pimpinan.

### 3.14 Rating Pengajar Mingguan (Peserta)
- Sekali per minggu per pengajar kelasnya: rating 1–5, sub-aspek penguasaan materi, cara menyampaikan, kedisiplinan, interaksi, komentar, saran, opsi **anonim**.
- Pengajar melihat rata-rata dan komentar (tanpa nama jika anonim). Admin dan pimpinan melihat nama.

### 3.15 Asrama: Daftar Hadir Malam
- Setiap malam dalam jendela waktu (default 20.00–21.30 WITA, dapat diatur per asrama), peserta melakukan **absen asrama**: selfie + verifikasi wajah + geofence lokasi asrama. Status awal `pending_confirmation`.
- **Pengelola asrama** membuka daftar per asrama/kamar, lalu **mengonfirmasi** (per orang atau per kamar sekaligus) bahwa peserta benar di asrama. Bisa juga **apel manual**: mencentang peserta yang hadir tanpa absen mandiri.
- Status: `pending_confirmation`, `confirmed`, `not_present`, `on_leave` (izin keluar disetujui), `sick_in_room`.
- Setelah jam malam, peserta yang belum absen ditandai merah dan notifikasi dikirim ke pengelola asrama (dan wali bila diaktifkan).

### 3.16 Izin Keluar Asrama
- Peserta mengajukan: jenis (`temporary`, `home_visit`, `overnight`), alasan, tujuan, pendamping, nomor kontak, rencana waktu keluar & kembali, lampiran opsional.
- Untuk jenis yang diatur (default `home_visit` dan `overnight`), **wali menyetujui dulu** lewat portal wali, lalu diteruskan ke pengelola asrama.
- Pengelola menyetujui/menolak dengan catatan. Jika disetujui, peserta mendapat **QR izin keluar**. Pengelola memindai QR saat peserta keluar dan kembali untuk mencatat waktu aktual.
- Status: `submitted`, `guardian_approved`, `approved`, `rejected`, `out`, `returned`, `late_return`, `cancelled`.
- Otomatis `late_return` jika melewati rencana waktu kembali; notifikasi ke pengelola dan wali.
- Batasan dari pengaturan: maks. pengajuan per minggu, minimal X jam sebelum keluar, tidak boleh bertabrakan dengan jadwal sesi kecuali jenis darurat.

### 3.17 Keperluan & Keluhan Asrama
- Kelola inventaris kamar (barang, jumlah, kondisi).
- Peserta melaporkan keluhan/permintaan (`broken_facility`, `cleanliness`, `supplies`, `other`) dengan foto; tim kerja memproses hingga selesai (`new`, `in_progress`, `resolved`) dengan catatan penyelesaian.
- Pengumuman khusus asrama: jadwal piket, kerja bakti, aturan.

### 3.18 Laporan Sakit di Asrama
- Peserta (atau teman sekamar/petugas atas namanya) melaporkan: pilihan gejala (demam, pusing, mual, diare, batuk/pilek, nyeri, sesak, lainnya), deskripsi, sejak kapan, suhu (opsional), tingkat keparahan (`mild`, `moderate`, `severe`, `emergency`), foto opsional.
- Notifikasi langsung ke **pengelola asrama** dan **Tim Kerja** unit asrama, kesehatan, dan konsumsi, serta ke wali.
- **Tombol DARURAT:** notifikasi prioritas tinggi ke semua petugas yang bertugas, menampilkan nama, asrama, nomor kamar, riwayat penyakit/alergi, dan tombol telepon cepat. Rate limit ringan, setiap penekanan tetap tercatat.
- Penanganan berupa timeline: petugas, tindakan (`examination`, `medication`, `rest`, `referral`, `monitoring`, `resolved`), obat & dosis, tempat rujukan, catatan kondisi, jadwal pemantauan berikutnya (dengan pengingat).
- Status laporan: `reported`, `handled`, `monitoring`, `referred`, `recovered`.
- Integrasi otomatis:
  - Membuat **izin sakit kelas** untuk hari tersebut (dikonfirmasi petugas).
  - Memberi tahu **unit konsumsi** bila perlu makanan khusus atau diantar ke kamar.
  - Absen asrama malam itu berstatus `sick_in_room`.
- Data kesehatan rahasia: detail hanya untuk pengelola asrama, tim kerja terkait, super admin, dan wali peserta tersebut. Pimpinan hanya melihat statistik.

### 3.19 Portal Orang Tua / Wali
- Akun dibuat lewat undangan: admin mengirim kode/tautan via WhatsApp/email, atau peserta membagikan kode dari profilnya. Satu wali bisa terhubung ke beberapa peserta; hubungan diverifikasi admin.
- Login nomor HP/email + password (opsional OTP WhatsApp).
- Tampilan sederhana, bahasa mudah dipahami:
  - Kalender kehadiran kelas & asrama (warna: hadir/terlambat/izin/sakit/alpa)
  - Ringkasan nilai periodik, pretest–posttest, persentase pengumpulan tugas
  - Catatan pengajar yang ditandai "boleh dilihat wali"
  - Status izin keluar dan laporan sakit
  - Level, poin, dan lencana anak
  - Pengumuman, jadwal kegiatan, kontak pengelola asrama
- Persetujuan wali untuk izin keluar jenis tertentu (3.16).
- Notifikasi: anak alpa/belum absen asrama, izin keluar disetujui, terlambat kembali, laporan sakit (terutama berat/darurat), nilai bulanan terbit.
- Wali **tidak** dapat melihat selfie, data peserta lain, atau rating anonim.

### 3.20 Gamifikasi
- Poin (XP) dari aturan yang dapat diatur admin (`gamification_rules`). Nilai awal:

| Event | Poin |
|---|---|
| Absen harian tepat waktu | +10 |
| Input kode sesi | +5 |
| Absen asrama tepat waktu | +5 |
| Tugas tepat waktu | +15 |
| Nilai tugas ≥ 85 | +10 |
| Quiz | skor% ÷ 5 (maks. +20), skor sempurna bonus +10 |
| Isi rating vendor/pengajar mingguan | +5 |
| Isi evaluasi program | +20 |
| Alpa (opsional, default nonaktif) | −10 |
| Terlambat kembali ke asrama (opsional, default nonaktif) | −10 |

- Semua perubahan poin tercatat di `point_transactions` (transparan, ada riwayat untuk peserta).
- **Level bertema bumbu woku:** Kemangi (0) → Serai (300) → Jahe (800) → Kunyit (1500) → Woku Komplit (2500).
- **Lencana**, contoh: "Nyanda Absen 7 Hari", "Raja Quiz", "Tugas Selalu Tepat Waktu", "Pande Speaking", "Anak Asrama Teladan", "Si Paling Rajin Isi Rating".
- **Streak harian** (hari berturut-turut hadir dan aktif) dengan animasi kecil di beranda.
- **Leaderboard** mingguan per kelas dan antarkelas; peserta bisa menyembunyikan nama (tampil inisial).
- "Peserta Terbaik Minggu Ini" di beranda dan dashboard pimpinan; dapat dicetak sebagai piagam apresiasi.
- Diimplementasikan lewat Event & Listener (`DailyAttendanceRecorded`, `SessionAttendanceRecorded`, `AssignmentSubmitted`, `AssignmentGraded`, `QuizCompleted`, `RatingSubmitted`, `EvaluationSubmitted`, `DormAttendanceConfirmed`). Pastikan idempoten (poin tidak dobel untuk event yang sama).

### 3.21 Evaluasi Program
- Admin membuat formulir evaluasi (form builder sederhana): Likert 1–5, pilihan ganda, isian.
- Template aspek: materi, pengajar, metode, fasilitas asrama, konsumsi, layanan tim kerja, manajemen program, kesiapan kerja/berangkat, saran umum.
- Responden: peserta (wajib), wali dan pengajar (opsional). Periode: tengah program, akhir program, atau custom.
- Mode anonim: simpan hanya hash responden (HMAC dari user id + id evaluasi) untuk mencegah pengisian ganda.
- Evaluasi akhir bisa dijadikan **syarat unduh sertifikat**.
- Hasil: grafik per aspek, Indeks Kepuasan 0–100, daftar saran, perbandingan antarangkatan.

### 3.22 Pengumuman, Notifikasi, Word of the Day
- Pengumuman dengan sasaran: semua, per kelas, per asrama, per role. Tanda sudah dibaca.
- Notifikasi Laravel (channel `database` + `mail` + `whatsapp`). Channel WhatsApp memakai driver yang bisa diganti (`log` untuk lokal, gateway pihak ketiga untuk produksi) lewat interface `WhatsAppGateway`.
- Pusat notifikasi di aplikasi (ikon lonceng + halaman daftar).
- **Word of the Day** per bahasa di beranda peserta: kata, bacaan (pinyin/romaji/pelafalan), arti, contoh kalimat, audio opsional. Dikelola pengajar.

### 3.23 Dashboard per Role
- **Peserta:** status absen hari ini, sesi hari ini + tombol input kode, tugas mendekati tenggat, quiz aktif, jadwal makan aktif, status absen asrama, poin/level/streak, Word of the Day, pengumuman.
- **Pengajar:** sesi hari ini, peserta belum hadir, tugas/quiz perlu dinilai, penilaian belum terbit, rating minggu ini.
- **Tim kerja:** sesuai unit — porsi hari ini, belum tanda tangan, absen asrama malam ini, izin keluar aktif, keluhan terbuka, laporan sakit aktif.
- **Admin program:** ringkasan angkatan, kehadiran, peringatan dini.

### 3.24 Dashboard Pemantauan Pimpinan
Read-only, filter angkatan, kelas, bahasa, pengajar, rentang tanggal; **drill-down** dari ringkasan ke detail kelas lalu peserta. Data di-cache 5 menit.
- **Ringkasan program:** jumlah peserta, kelas, pengajar; progres pelatihan (sesi selesai ÷ sesi terjadwal); hari tersisa.
- **Kehadiran:** persentase harian, per sesi, dan asrama; tren harian (line); perbandingan antarkelas (bar); jumlah terlambat, izin, sakit, alpa.
- **Akademik:** rata-rata komponen sesi dan aspek bahasa per kelas (radar); tren nilai mingguan/bulanan; hasil quiz dan tryout; **pretest–posttest & N-Gain**; distribusi predikat; tingkat pengumpulan tugas.
- **Kinerja pengajar:** kelengkapan input (sesi terlaksana, komponen dinilai, catatan diberikan, penilaian terbit tepat waktu, tugas/quiz dinilai) dan rata-rata rating peserta.
- **Konsumsi:** porsi terdistribusi vs dipesan, rating vendor per minggu.
- **Asrama:** kehadiran malam, peserta sedang izin keluar, keterlambatan kembali, keluhan terbuka vs selesai, statistik kasus sakit (tanpa detail medis).
- **Evaluasi program:** indeks kepuasan per aspek.
- **Keterlibatan:** leaderboard antarkelas, peserta terbaik, jumlah wali terdaftar & aktif.
- **Peringatan dini:** peserta dengan kehadiran < target, nilai di bawah minimal, nilai turun 2 minggu berturut-turut, sering terlambat kembali ke asrama, tugas sering tidak dikumpulkan.
- **Profil peserta 360°:** kehadiran, nilai sesi, quiz, pretest–posttest, tugas, catatan pengajar, catatan asrama dalam satu halaman.
- Unduh **ringkasan eksekutif** PDF mingguan/bulanan; email ringkasan otomatis tiap Senin 07.00 WITA (dapat dimatikan).

### 3.25 Sertifikat
- PDF otomatis di akhir program untuk peserta yang memenuhi syarat kehadiran dan nilai (dan evaluasi akhir bila diwajibkan).
- Nomor sertifikat unik dan **QR verifikasi** ke halaman publik `/verifikasi/{token}` (hanya menampilkan nama, program, angkatan, status valid).

---

## 4. Skema Database

Konvensi: `id` bigint, `timestamps` di semua tabel, `softDeletes` pada data master dan transaksi penting. Kolom status memakai string + PHP enum. Foreign key dengan `constrained()`; index pada kolom tanggal dan status.

### 4.1 Pengguna & Program
- `users`: name, email (nullable, unique), username (NIS/NIP, unique), phone, password, photo_path, is_active, consented_at, consent_version, last_login_at
- `staff_units`: user_id, unit (`consumption`|`dormitory`|`health`) — unique(user_id, unit)
- `batches`: name, start_date, end_date, attendance_target, passing_score, is_active
- `languages`: code, name, exam_format, reading_label (mis. "Pinyin", "Romaji")
- `classrooms`: batch_id, language_id, name, level, teacher_id, location_id
- `classroom_user`: classroom_id, user_id, joined_at — unique
- `participant_profiles`: user_id, school_name, major, gender, birth_date, allergies, medical_history (encrypted), emergency_contact_name, emergency_contact_phone, face_reference_path, face_descriptor (encrypted json), target_exam, target_score
- `locations`: name, type (`classroom`|`dormitory`), latitude, longitude, radius_m
- `settings`: key (unique), value (json)

### 4.2 Kehadiran & Pembelajaran
- `daily_attendances`: user_id, date, checked_in_at, selfie_path, face_distance, latitude, longitude, accuracy_m, distance_m, status, reject_reason, approved_by, device_info — unique(user_id, date)
- `learning_sessions`: classroom_id, teacher_id, date, start_time, end_time, topic, objectives, status (`scheduled`|`ongoing`|`completed`|`cancelled`), started_at, ended_at
- `session_attendance_codes`: learning_session_id, code, expires_at, is_active, generated_by
- `session_attendances`: learning_session_id, user_id, attendance_code_id, submitted_at, latitude, longitude, status (`present`|`late`|`excused`|`sick`|`absent`), marked_by, note — unique(learning_session_id, user_id)
- `session_materials`: learning_session_id (nullable untuk bank materi), teacher_id, language_id, title, description, type (`file`|`audio`|`video`|`link`), file_path, url, original_name, mime, size, visible_from, is_downloadable, sort_order
- `material_access_logs`: session_material_id, user_id, viewed_at, downloaded_at
- `assessment_component_templates`: teacher_id, name, aspect, weight, max_score
- `session_assessment_components`: learning_session_id, name, aspect, weight, max_score, source (`manual`|`quiz`|`assignment`), source_id
- `session_scores`: session_assessment_component_id, user_id, score, note — unique(component, user)
- `session_notes`: learning_session_id, user_id, teacher_id, aspect, note, visible_to_guardian, read_at
- `assessments`: classroom_id, user_id, teacher_id, period_type (`weekly`|`monthly`), period_start, period_end, listening, speaking, reading, writing, vocabulary, grammar, attitude, average, grade, feedback, visible_to_guardian, status (`draft`|`published`), published_at — unique(classroom_id, user_id, period_type, period_start)
- `leave_requests`: user_id, date_from, date_to, type (`excused`|`sick`), reason, attachment_path, status (`pending`|`approved`|`rejected`), reviewed_by, reviewed_at, health_report_id (nullable)

### 4.3 Tugas
- `assignments`: classroom_id, learning_session_id (nullable), teacher_id, title, instructions, submission_type, due_at, max_score, allow_late, late_penalty_percent, allow_resubmit, status (`draft`|`published`|`closed`)
- `assignment_attachments`: assignment_id, file_path, original_name, mime, size
- `assignment_submissions`: assignment_id, user_id, text_answer, submitted_at, is_late, score, final_score, feedback, feedback_audio_path, status, revision_count, graded_by, graded_at — unique(assignment_id, user_id)
- `submission_files`: assignment_submission_id, file_path, mime, size

### 4.4 Quiz
- `question_banks`: teacher_id, language_id, level, topic
- `questions`: question_bank_id, type, body, reading (pinyin/romaji, nullable), media_path, points, explanation
- `question_options`: question_id, body, reading, is_correct, match_key
- `quizzes`: classroom_id, teacher_id, learning_session_id (nullable), type (`quiz`|`pretest`|`posttest`|`tryout`), title, instructions, opens_at, closes_at, duration_minutes, max_attempts, passing_score, shuffle_questions, shuffle_options, result_visibility, status
- `quiz_question`: quiz_id, question_id, sort_order, points
- `quiz_attempts`: quiz_id, user_id, started_at, submitted_at, score, max_score, percentage, status (`in_progress`|`submitted`|`needs_grading`|`graded`), tab_switch_count, ip, device_info
- `quiz_answers`: quiz_attempt_id, question_id, answer (json), audio_path, is_correct, score, teacher_feedback

### 4.5 Konsumsi & Rating
- `vendors`: name, contact_person, phone, address, is_active
- `meal_schedules`: date, meal_type, vendor_id, menu, starts_at, ends_at, portions_ordered, created_by — unique(date, meal_type, vendor_id)
- `meal_receipts`: meal_schedule_id, user_id, signature_path, received_at, latitude, longitude, proxy_by (nullable), note — unique(meal_schedule_id, user_id)
- `special_meal_requests`: user_id, health_report_id, date, meal_type, request, delivered_to_room, status
- `vendor_ratings`: vendor_id, user_id, week_start, rating, taste, portion, cleanliness, punctuality, comment, suggestion — unique(vendor_id, user_id, week_start)
- `teacher_ratings`: teacher_id, user_id, classroom_id, week_start, rating, mastery, delivery, discipline, interaction, comment, suggestion, is_anonymous — unique(teacher_id, user_id, week_start)

### 4.6 Asrama & Kesehatan
- `dormitories`: name, gender, location_id, capacity, curfew_time, check_window_start, check_window_end
- `dorm_rooms`: dormitory_id, number, floor, capacity
- `dorm_room_assignments`: dorm_room_id, user_id, check_in_date, check_out_date
- `dorm_attendances`: user_id, dormitory_id, date, checked_in_at, selfie_path, face_distance, latitude, longitude, distance_m, status, confirmed_by, confirmed_at, note — unique(user_id, date)
- `dorm_exit_permits`: user_id, type, reason, destination, companion, contact_phone, planned_departure, planned_return, attachment_path, status, guardian_id, guardian_decided_at, approved_by, approval_note, actual_departure, actual_return, qr_token (unique)
- `dorm_requests`: user_id, dorm_room_id, category, description, photo_path, priority, status, handled_by, resolution_note, resolved_at
- `dorm_inventories`: dorm_room_id, item_name, quantity, condition
- `health_reports`: user_id, reported_by, dormitory_id, dorm_room_id, symptoms (json), description (encrypted), started_at, temperature, severity, photo_path, is_emergency, status, reported_at, resolved_at
- `health_report_actions`: health_report_id, handled_by, action_type, medicine, dosage, referral_place, condition_note (encrypted), next_check_at, acted_at

### 4.7 Wali, Gamifikasi, Evaluasi, Lainnya
- `guardian_participant`: guardian_id, participant_id, relationship (`father`|`mother`|`guardian`), is_primary, verified_at, verified_by
- `guardian_invitations`: participant_id, phone, email, code (unique), expires_at, used_at
- `gamification_rules`: event_key (unique), name, points, max_per_day, is_active
- `point_transactions`: user_id, event_key, points, reference_type, reference_id, description — unique(user_id, event_key, reference_type, reference_id)
- `levels`: name, min_points, icon
- `badges`: name, slug, description, icon, criteria (json)
- `badge_user`: badge_id, user_id, earned_at
- `streaks`: user_id (unique), current_streak, longest_streak, last_activity_date
- `program_evaluations`: batch_id, title, period, target_role, is_anonymous, required_for_certificate, opens_at, closes_at, status
- `evaluation_questions`: program_evaluation_id, aspect, type, body, options (json), is_required, sort_order
- `evaluation_responses`: program_evaluation_id, user_id (nullable), respondent_hash (unique per evaluasi), submitted_at
- `evaluation_answers`: evaluation_response_id, evaluation_question_id, value
- `announcements`: title, body, audience (`all`|`classroom`|`dormitory`|`role`), target_id, target_role, published_at, created_by
- `announcement_reads`: announcement_id, user_id, read_at
- `words_of_the_day`: language_id, date, word, reading, meaning, example, audio_path — unique(language_id, date)
- `certificates`: user_id, batch_id, number (unique), issued_at, verification_token (unique), file_path
- Tabel bawaan: `notifications`, `jobs`, `failed_jobs`, `job_batches`, `cache`, `sessions`, `activity_log` (spatie), tabel spatie permission.

---

## 5. Laporan (Excel & PDF)

Semua laporan memiliki filter angkatan, kelas, bahasa, dan rentang tanggal. Ekspor besar dijalankan sebagai Job dan pengguna mendapat notifikasi saat file siap diunduh.

1. **Penerimaan konsumsi** — harian, mingguan, bulanan: peserta, jadwal makan, waktu terima, gambar tanda tangan (PDF), penerimaan diwakilkan; total porsi per vendor (dasar pembayaran vendor).
2. **Daftar hadir kelas** — mingguan & bulanan: absen harian + kehadiran per sesi, persentase per peserta, rekap hadir/terlambat/izin/sakit/alpa.
3. **Daftar hadir asrama** — harian, mingguan, bulanan, beserta nama petugas yang mengonfirmasi.
4. **Izin keluar asrama** — waktu rencana vs aktual, keterlambatan, persetujuan wali.
5. **Rekap nilai** — komponen sesi, penilaian mingguan/bulanan, quiz, tryout, pretest–posttest & N-Gain.
6. **Rekap tugas** — status pengumpulan per peserta dan nilai.
7. **Rekap rating** vendor dan pengajar per minggu beserta komentar.
8. **Rekap keluhan asrama** — kategori dan waktu penyelesaian.
9. **Rekap kesehatan asrama** — versi lengkap (pengelola asrama/tim kesehatan) dan versi statistik (pimpinan).
10. **Hasil evaluasi program** per aspek beserta saran.
11. **Rekap poin & lencana.**
12. **Rekap akses materi** per sesi.
13. **Ringkasan eksekutif pimpinan.**

Format PDF resmi: kop dengan logo Wokabulari dan Poltekkes Kemenkes Manado, judul, periode, tabel, kolom tanda tangan penanggung jawab, nomor halaman, waktu cetak. Daftarkan font Noto Sans SC/JP di dompdf agar teks Mandarin/Jepang tidak tampil sebagai kotak.

---

## 6. Keamanan & Privasi

- Policy untuk setiap model; pimpinan read-only; wali hanya data anak yang terhubung dan terverifikasi.
- Validasi lokasi, waktu, durasi quiz, dan jendela absen di **server**.
- Descriptor wajah referensi tidak pernah dikirim ke browser; pencocokan dilakukan di server.
- Disk privat untuk selfie, tanda tangan, audio, lampiran, dan foto keluhan/sakit; diakses lewat signed URL berumur pendek.
- Cast `encrypted` untuk riwayat penyakit, catatan medis, dan face descriptor.
- Validasi tipe (MIME sebenarnya) dan ukuran semua unggahan; nama file disimpan dengan UUID.
- Rate limit: login, input kode sesi, tombol darurat, ekspor laporan.
- Satu sesi login aktif saat mengerjakan quiz.
- Audit trail (spatie/laravel-activitylog) untuk perubahan nilai, status kehadiran, persetujuan izin, data kesehatan, pengaturan.
- **Retensi data:** selfie absen dihapus otomatis 90 hari setelah angkatan selesai (dapat diatur); data kesehatan disimpan sesuai kebijakan instansi.
- HTTPS wajib di produksi (kamera, mikrofon, GPS).

---

## 7. Scheduler & Antrean

| Jadwal (WITA) | Tugas |
|---|---|
| Tiap menit | Nonaktifkan kode sesi kedaluwarsa; auto-submit attempt quiz yang habis waktunya |
| Tiap 15 menit | Cek izin keluar melewati waktu kembali → `late_return` + notifikasi |
| Tiap 15 menit, dalam/sesudah jendela absen asrama | Kirim daftar peserta belum absen asrama ke pengelola |
| Tiap jam | Pengingat pemantauan laporan sakit (`next_check_at`) |
| Tiap 30 menit | Pengingat tugas yang tenggatnya 2 jam lagi |
| 00.05 | Perbarui streak; tutup absen asrama hari sebelumnya |
| 06.00 | Publikasikan Word of the Day |
| 18.00 | Pengingat tugas H-1 |
| 19.00 | Peringatan kehadiran di bawah target |
| Jumat 15.00 | Pengingat rating vendor & pengajar |
| Senin 07.00 | Email ringkasan mingguan ke pimpinan |
| 01.00 | Backup database + file (spatie/laravel-backup) |
| 02.00 | Bersihkan backup lama; hapus file sementara |

Antrean Redis dengan queue terpisah: `default`, `notifications`, `exports`.

---

## 8. Seeder (data contoh untuk uji localhost)

- 1 super admin, 1 pimpinan, 1 admin program
- 4 pengajar: Inggris, Mandarin, Jepang, Jerman (masing-masing 1 kelas)
- 3 tim kerja: unit konsumsi, unit asrama, unit kesehatan
- 32 peserta (8 per kelas) dengan profil, sebagian punya alergi
- 32 akun wali terhubung ke peserta
- Lokasi kampus dan asrama Poltekkes Kemenkes Manado (koordinat contoh, dapat diubah di pengaturan)
- 2 asrama (putra & putri) dengan kamar dan penempatan
- 2 vendor; jadwal makan 2 minggu
- Jadwal sesi 2 minggu, komponen penilaian, materi contoh, 5 tugas + pengumpulan
- Bank soal per bahasa (termasuk soal hanzi + pinyin dan soal nada), 1 pretest, 2 quiz, 1 tryout HSK 1
- Data absen harian, sesi, dan asrama 2 minggu dengan variasi status
- 3 laporan sakit dengan riwayat penanganan, 2 izin keluar, 3 keluhan
- Aturan gamifikasi, 5 level, 10 lencana; 1 formulir evaluasi tengah program
- Word of the Day 14 hari per bahasa

Akun seeder (khusus lokal, password `password`): `admin@wokabulari.test`, `pimpinan@wokabulari.test`, `program@wokabulari.test`, `pengajar.en@wokabulari.test`, `pengajar.zh@wokabulari.test`, `pengajar.ja@wokabulari.test`, `pengajar.de@wokabulari.test`, `konsumsi@wokabulari.test`, `asrama@wokabulari.test`, `kesehatan@wokabulari.test`, peserta `peserta01`…`peserta32` (login dengan NIS), wali `wali01@wokabulari.test`…

Buat juga perintah `php artisan app:create-admin` untuk membuat super admin di produksi (tanpa seeder data contoh).

---

## 9. Pengujian

Feature Test (Pest) minimal untuk:
- Login dengan email dan NIS/NIP; persetujuan data pribadi
- Absen harian: di dalam/luar radius, wajah cocok/tidak, duplikat hari yang sama, status terlambat
- Kode sesi: kedaluwarsa, salah kelas, belum absen harian, rate limit, penandaan otomatis saat sesi selesai
- Skor komponen sesi dan perhitungan nilai tertimbang
- Tugas: pengumpulan terlambat dengan potongan nilai, revisi
- Quiz: penilaian otomatis tiap tipe soal, kunci jawaban tidak bocor di respons, auto-submit, N-Gain
- Konsumsi: tanda tangan di luar jendela waktu ditolak, penerimaan diwakilkan
- Rating mingguan: tidak bisa dua kali dalam minggu yang sama; anonim tersembunyi dari pengajar
- Asrama: absen + konfirmasi pengelola, izin keluar dengan persetujuan wali, `late_return`
- Laporan sakit: notifikasi terkirim ke pihak yang benar, izin sakit kelas otomatis, privasi data medis (pimpinan tidak bisa melihat detail)
- Portal wali: tidak bisa melihat peserta lain
- Gamifikasi: poin idempoten, lencana terbuka sesuai kriteria
- Evaluasi anonim: tidak bisa mengisi ganda
- Ekspor laporan: job berjalan dan file terbentuk
- Policy: setiap role hanya bisa mengakses yang diizinkan (dataset per role)
