# Catatan Versi Wokabulari

Versi ditampilkan di halaman login dan menu aplikasi (`config/wokabulari.php` → `version`).
Aturan penomoran: **patch** (1.0.x) perbaikan, **minor** (1.x.0) fitur baru, **major** (x.0.0) perubahan besar.

## 1.13.0 — 3 November 2026

- **Absen terlambat tetap bisa, tercatat Terlambat, dan poin berkurang:**
  - Absen harian lewat batas tepat waktu: halaman absen memperingatkan sebelum absen ("tercatat TERLAMBAT, −5 poin") dan menampilkan status Terlambat setelahnya.
  - Absen asrama lewat jendela absen masih bisa sampai **batas terlambat** (Pengaturan Sistem → Asrama → *Absen terlambat s.d.*, bawaan 23.59), ditandai *Terlambat* di halaman peserta & Absen Malam; pengurangan poin berlaku saat absen dikonfirmasi.
- **Punishment point di Komponen Poin:** kelompok *Pelanggaran (pengurang poin)* kini berisi **Terlambat absen harian** dan **Terlambat absen asrama** (bawaan −5, aktif, bisa diubah/dimatikan) selain Alpa dan Terlambat kembali ke asrama.
- **Poin & Lencana peserta:** total *pengurangan minggu ini* dan filter riwayat **Semua / Penambahan / Pengurangan**.

## 1.12.0 — 2 November 2026

- **Izin keluar asrama juga disetujui admin program:** admin program kini bisa menyetujui/menolak izin di Asrama → Izin Keluar (bersama pengelola asrama) dan ikut diberi tahu saat ada izin yang perlu ditinjau.
- **Penyimpanan & cadangan** (Pengaturan Sistem): pemakaian disk VPS (terpakai, total, sisa; peringatan bila sisa < 15%) dan rincian yang dipakai Wokabulari (database, berkas privat, berkas publik, cadangan, log), tombol **Perbarui**.
- **Tombol Buat cadangan sekarang:** membuat ZIP berisi dump database + berkas privat & publik di antrean; pemberi perintah diberi tahu saat siap. Daftar 5 cadangan terbaru bisa **diunduh** atau dihapus (hanya super admin). Cadangan harian otomatis lewat `scripts/backup.sh` tetap berjalan.

## 1.11.0 — 2 November 2026

- **Konfirmasi otomatis absen asrama** (Pengaturan Sistem → Asrama): bila dinyalakan, absen malam mandiri yang lolos verifikasi wajah & lokasi langsung **terkonfirmasi** (bertanda "otomatis" di halaman Absen Malam), dan absen yang masih menunggu saat tombol dinyalakan ikut dikonfirmasi. Bila dimatikan (bawaan), pengelola asrama mengonfirmasi manual seperti sebelumnya. Apel manual & penandaan tetap bisa dilakukan di kedua mode.

## 1.10.0 — 2 November 2026

- **Tim pengajar/tim kerja penerima konsumsi:** di Pengguna & Tim, aktifkan *Menerima konsumsi*. Akun tersebut mendapat menu **Konsumsi Saya**, menandatangani penerimaan makan/snack sama seperti peserta, diberi tahu saat makanan siap, dan ikut dihitung di porsi, Monitor Makan (berlabel *Tim pengajar/kerja*), serta laporan konsumsi (lembar *Per penerima*). Jenis konsumsinya diatur tim konsumsi lewat tombol **Penerima konsumsi** di Jadwal Makan (bawaan: snack pagi, makan siang, snack sore).
- **Kehadiran & laporan mengajar:** pengajar otomatis **tercatat hadir** di sesi bila ia membuat kode kehadiran dan ada peserta yang masuk (halaman sesi menampilkan statusnya). **Laporan mengajar** tiap pengajar — jumlah sesi, hadir, kode tanpa peserta, tidak tercatat, jam mengajar, rata-rata peserta hadir, plus rincian per sesi — tersedia di Pusat Laporan (filter pengajar) dan untuk pengajar sendiri di Mengajar → **Laporan Mengajar**, dengan unduh Excel.

## 1.9.1 — 31 Oktober 2026

- Perbaikan pemasangan: daftar kata Mandarin awal dipindah ke `database/vocabulary/` (folder `database/data/` sengaja tidak ikut image Docker), dan migrasi bank kosakata aman diulang bila tabelnya sudah terbuat dari percobaan yang gagal.

## 1.9.0 — 31 Oktober 2026

- **Import Bank Kosakata dari Excel/CSV** (Pendampingan → Bank Kosakata → *Import Excel*): pilih bahasa, **unduh templat** (lembar *Kosakata* berisi contoh sesuai bahasa + lembar *Petunjuk*), isi lalu unggah. Kolom: kata, cara_baca, arti, topik, contoh, arti_contoh, urutan, aktif (kata & arti wajib). Kata yang sudah ada di bahasa itu diperbarui, bukan digandakan; baris bermasalah dilewati dan dilaporkan per nomor baris.

## 1.8.0 — 31 Oktober 2026

- **Bagikan lencana ke media sosial:** di Poin & Lencana (atau dari notifikasi lencana baru), peserta membuat kartu bergaya Wokabulari — format *Story 9:16* atau *Kotak 1:1*, nama bisa disembunyikan — lalu **Bagikan** langsung ke Instagram/WhatsApp/TikTok dari HP atau **Unduh gambar**. Gambar dibuat di HP, tidak diunggah ke server.
- **Kosakata hari ini** di beranda peserta: 5 kartu kata dari bank bahasa kelasnya, **geser** untuk kartu berikutnya, **ketuk** untuk melihat arti, tombol **Dengar** untuk pelafalan; berganti otomatis tiap hari. Bank awal **Mandarin** (122 kata: sapaan, angka & waktu, tubuh, kesehatan & perawatan, makan, kegiatan); kelas yang bank bahasanya belum diisi sementara melihat Mandarin.
- **Bank Kosakata** (Pendampingan, pengajar & admin program): tambah/ubah/nonaktifkan kata per bahasa dan lihat 5 kata hari ini.

## 1.7.0 — 31 Oktober 2026

- **Input konsumsi oleh tim konsumsi** (Konsumsi → *Input Konsumsi*): vendor dipilih per input, beserta jumlah (pcs/dos), menu, jam siap, tempat, dan foto. Makan utama selalu tampil per hari; **snack ditambahkan tim hanya bila tersedia** dan tidak ikut "Salin ke minggu depan". Akun login vendor dihapus dari alur (akun lama dinonaktifkan). Notifikasi jam & tempat makanan siap tetap jalan.
- **Master Komponen Poin** (Program → Komponen Poin): poin absen harian, kode sesi, **tanda terima konsumsi** (baru, +2), absen asrama, tugas, quiz, dll. bisa diubah; komponen tambahan bisa ditambah. **Pengajar mengajukan komponen** (mis. tugas proyek, juara quiz), admin menyetujui/menolak, lalu pengajar memberi poin ke peserta kelasnya di Mengajar → *Poin Peserta*.
- **Panduan Penggunaan** (Bantuan): langkah per role — peserta, wali, pengajar, tim konsumsi, asrama, kesehatan, admin program, pimpinan, super admin.
- **Dashboard Eksekutif** (pimpinan & admin program): angka utama, catatan untuk keputusan, tren kehadiran harian, sebaran status absen, perbandingan antarkelas, tren & sumber nilai, predikat, porsi dipesan/diterima/tanda terima, serapan per jenis konsumsi, daftar *Perlu perhatian*, dan grafik per peserta.
- **Pusat Laporan**: absen harian (siapa hadir), absen per sesi, konsumsi diterima, input konsumsi tim, dan rekap nilai — harian/mingguan/bulanan, filter angkatan & kelas, **unduh Excel**.
- **Biodata peserta** (menu profil → *Biodata*): tempat & tanggal lahir, jenis kelamin (sekali isi), alamat, sekolah & kelas, golongan darah, alergi, riwayat penyakit, kontak darurat, ukuran baju, target ujian; ada persentase kelengkapan dan pengingat di beranda. Admin melihatnya di detail peserta.
## 1.6.0 — 31 Oktober 2026

- **Akun Vendor Makanan:** dibuat tim konsumsi di Master → Vendor → *Akun login* (login pakai No. HP, wajib ganti kata sandi di awal; bisa reset/nonaktifkan).
- **Lapor pengantaran** (vendor): jumlah (pcs/dos), menu, tanggal terima, jam siap diambil, tempat makanan tersedia, foto menu, dan foto total. Bisa untuk jadwal dari tim konsumsi atau langsung (jadwal dibuat otomatis); pengambilan dibuka mulai jam siap.
- **Notifikasi makanan siap:** tepat pada jam siap, peserta yang berhak dan belum mengambil diberi tahu *jam & tempat* (plus kartu "siap diambil" di beranda dan info tempat di halaman Konsumsi).
- Monitor makan menampilkan laporan vendor (jumlah, jam siap, tempat, foto).

## 1.5.1 — 30 Oktober 2026

- **Pengguna & Tim:** satu akun bisa punya beberapa role, mis. **Tim Kerja + Pengajar**. Unit tim kerja wajib dipilih bila role Tim Kerja dicentang. Profil menampilkan semua role (mis. "Pengajar · Tim Kerja") dan beranda menampilkan akses cepat untuk tugas mengajar maupun unit.

## 1.5.0 — 30 Oktober 2026

- **Reset kata sandi massal** di halaman Peserta (centang → menu ⋯ → Reset kata sandi): kembali ke sandi awal dan wajib diganti saat login berikutnya.
- **Tampilan minimalis** halaman login dan beranda: latar putih, satu kolom, kartu datar, sapaan tanpa banner; logo mitra, versi, dan sidik HaKI tetap ada. Admin, pengajar, dan pimpinan mendapat **Akses cepat** ke menu yang paling sering dipakai.

## 1.4.0 — 30 Oktober 2026

- **Jadwal absen harian per angkatan** (Master → Angkatan): pilih hari absen (mis. Senin–Jumat), jam dibuka, batas tepat waktu, dan jam ditutup. Di luar jadwal peserta tidak bisa absen dan halaman absen menjelaskan alasannya; daftar angkatan menampilkan jadwalnya.
- Pengaturan sistem jam absen kini menjadi nilai bawaan untuk angkatan yang belum mengatur jadwal sendiri.

## 1.3.1 — 30 Oktober 2026

- Perbaikan pemasangan: nama indeks tabel survei dipersingkat (batas 64 karakter MySQL); migrasi membersihkan sisa tabel kosong dari percobaan yang gagal.

## 1.3.0 — 30 Oktober 2026

- **Survei & Evaluasi:** tim kerja membuat survei (templat *Sarana & prasarana* atau *Evaluasi program*, bisa diubah), memilih **angkatan**, responden **peserta** dan/atau **tim pengajar**, mode anonim, jadwal buka, dan **tenggat**. Responden diberi tahu saat survei dibuka dan diingatkan menjelang tenggat.
- Peserta & pengajar mengisi di menu **Survei** (pengingat juga di beranda); peserta mendapat +20 poin.
- Hasil survei: partisipasi, Indeks Kepuasan 0–100, nilai per aspek (prioritas perbaikan di atas), sebaran jawaban, dan daftar saran; pimpinan bisa melihat.
- **Kelas dengan lebih dari satu pengajar:** pengajar utama + pengajar lain; semua pengajar kelas punya akses yang sama dan dinilai peserta masing-masing.
- Tombol Keluar ganda di kanan atas dihapus (Keluar ada di menu profil).

## 1.2.0 — 26 Oktober 2026

- **Assign kelas massal:** di halaman Peserta, centang banyak peserta (atau "Pilih semua hasil" dari filter, mis. *Belum punya kelas*), lalu pindahkan ke satu kelas atau **bagi rata** ke beberapa kelas dengan pratinjau.
- **Assign asrama massal:** **isi kamar otomatis** sesuai jenis kelamin & sisa kapasitas, pindah ke kamar tertentu, atau tandai tinggal/tidak tinggal di asrama — dengan pratinjau. Jenis kelamin yang belum diisi bisa diisi massal (filter *Jenis kelamin belum diisi*).
- **Peserta tidak asrama:** tidak ikut absen malam, izin keluar, dan keluhan asrama; menu asrama disembunyikan. Bisa diatur dari form peserta atau kolom impor `asrama` (ya/tidak).
- **Konsumsi snack:** jenis baru *Snack pagi* dan *Snack sore* di jadwal makan.
- **Konsumsi peserta tidak asrama** diatur tim konsumsi (tombol *Peserta tidak asrama* di Jadwal makan); bawaan: snack pagi, makan siang, snack sore.

## 1.1.0 — 25 Oktober 2026

- Semua pengguna kecuali super admin bisa mengisi email sendiri (di Profil atau saat login pertama), lalu login memakai email atau No. HP.
- Email disimpan huruf kecil dan harus unik; No. HP dan email tidak boleh dikosongkan bersamaan.
- Email super admin hanya diatur lewat server.

## 1.0.0 — 25 Oktober 2026

Rilis pertama di https://wokabulari.poltekkes-manado.ac.id.

- Login dengan No. HP / email / NIS-NIP, wajib ganti kata sandi awal, persetujuan data pribadi.
- Absen harian (wajah + lokasi), sesi belajar & kode kehadiran, materi, izin/sakit kelas.
- Penilaian sesi & periodik, tugas/PR, quiz/pretest/posttest/tryout dengan analisis.
- Konsumsi bertanda tangan, rating vendor & pengajar mingguan.
- Asrama: absen malam, izin keluar ber-QR, keluhan & inventaris; laporan sakit + tombol darurat.
- Portal orang tua/wali, gamifikasi (poin, level, lencana, peringkat, piagam).
- Pengumuman dari Tim Kerja, Pengajar, dan Asrama.
- Tampilan mengikuti identitas Kemenkes; tombol Keluar di kanan atas; peserta wajib mengisi No. HP orang tua/wali.
- Hak cipta tertanam terenkripsi & bertanda tangan digital di kode.
