# Catatan Versi Wokabulari

Versi ditampilkan di halaman login dan menu aplikasi (`config/wokabulari.php` → `version`).
Aturan penomoran: **patch** (1.0.x) perbaikan, **minor** (1.x.0) fitur baru, **major** (x.0.0) perubahan besar.

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
