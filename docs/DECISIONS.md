# Catatan Keputusan Teknis

Keputusan yang menyimpang dari atau melengkapi `docs/SPEC.md` / `docs/DOCKER.md`, beserta alasannya.

| # | Tanggal | Keputusan | Alasan |
|---|---|---|---|
| 1 | 24 Okt 2026 | Starter kit Livewire diambil dari cabang `dev-main` (Laravel 13, Livewire 4, Flux 2). Fitur auth yang dipakai hanya login + lupa kata sandi; registrasi publik, passkey, 2FA, dan verifikasi email dihapus. | Tag rilis starter kit masih Laravel 12. Akun dibuat admin/import, wali lewat undangan (SPEC 3.19), sehingga registrasi publik tidak dibutuhkan. |
| 2 | 24 Okt 2026 | QR code memakai `bacon/bacon-qr-code` v3 langsung (lewat `App\Services\QrCodeService`), bukan `simplesoftwareio/simple-qrcode`. | `simple-qrcode` masih mewajibkan bacon v2, sedangkan Laravel Fortify memakai bacon v3 sehingga keduanya bentrok. |
| 3 | 24 Okt 2026 | Versi paket: `spatie/laravel-permission` v8, `spatie/laravel-activitylog` v5, `maatwebsite/excel` v4, `spatie/laravel-backup` v10, `barryvdh/laravel-dompdf` v3. | Versi mayor terbaru yang mendukung Laravel 13. |
| 4 | 24 Okt 2026 | Login menerima **No. HP**, email, atau NIS/NIP. Akun hasil import memakai kata sandi awal dari `PARTICIPANT_DEFAULT_PASSWORD` dan wajib mengganti kata sandi saat login pertama (`users.must_change_password`). | Permintaan pemilik program: peserta login dengan No. HP. Kata sandi awal tidak ditulis di repo. |
| 5 | 24 Okt 2026 | Data peserta asli (nama + No. HP) disimpan di `database/data/*.csv` yang di-*gitignore*; hanya `contoh-peserta.csv` (data palsu) yang masuk repo. Import lewat `php artisan app:import-participants <berkas>` atau menu Peserta → Import. | Repo GitHub bersifat publik; data pribadi tidak boleh ikut ter-push (UU PDP). |
| 6 | 24 Okt 2026 | File privat disimpan di disk `private` (`storage/app/private`) dan disajikan lewat *signed temporary URL* bawaan Laravel di prefiks `/berkas`. | Memenuhi SPEC 6 (signed URL berumur pendek) tanpa controller tambahan. |
| 7 | 24 Okt 2026 | Nama peserta hanya dapat diubah admin program. | Nama dipakai di sertifikat dan laporan resmi. |
| 8 | 24 Okt 2026 | Permission tim kerja diberikan sebagai *direct permission* sesuai unit (`staff_units`) lewat `StaffUnitService`; daftar ada di `config/wokabulari.php`. | Satu orang bisa lebih dari satu unit; role `staff` tetap satu. |
| 9 | 24 Okt 2026 | Path URL berbahasa Indonesia (`/beranda`, `/absen`), nama route berbahasa Inggris (`dashboard`, `attendance.daily`). | Mudah dibaca pengguna, konsisten dengan konvensi kode. |
| 10 | 24 Okt 2026 | Pengembangan di mesin ini memakai PHP 8.4/Node 22 dari Laragon dengan SQLite serta driver `database` untuk cache/queue/session. Konfigurasi Docker (`compose.yaml`, `Dockerfile`, `Makefile`) ditulis sesuai `docs/DOCKER.md`. | Docker belum terpasang di mesin pengembang. Test (`php artisan test`) tetap dijalankan dengan SQLite in-memory seperti di container. |
| 11 | 24 Okt 2026 | Logo mitra (Poltekkes Kemenkes Manado dan BP3MI Sulawesi Utara) ditampilkan di halaman login lewat komponen `x-partner-logos`. Logo BP3MI dibaca dari `public/images/logos/bp3mi.(svg|png|webp|jpg)`; bila belum ada, tampil teks nama instansi. | Berkas logo BP3MI resolusi baik belum tersedia di repo. |
| 12 | 25 Okt 2026 | Tabel `daily_attendances` ditambah kolom `approval_note` dan `attempts`. Satu enum `AttendanceStatus` (present, late, excused, sick, absent, rejected) dipakai absen harian dan kehadiran sesi. | Alasan penandaan manual perlu disimpan terpisah dari alasan penolakan; jumlah percobaan membantu pengajar meninjau absen yang ditolak. |
| 13 | 25 Okt 2026 | Pencarian kolom tanggal selalu memakai `whereDate`/macro `whereDateBetween`. | SQLite (test) menyimpan kolom DATE beserta jam, sedangkan MySQL tidak; `whereDate` aman di keduanya. |
