# Catatan Versi Wokabulari

Versi ditampilkan di halaman login dan menu aplikasi (`config/wokabulari.php` → `version`).
Aturan penomoran: **patch** (1.0.x) perbaikan, **minor** (1.x.0) fitur baru, **major** (x.0.0) perubahan besar.

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
