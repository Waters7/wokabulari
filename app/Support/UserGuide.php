<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Models\User;

/**
 * Isi menu Panduan: langkah penggunaan aplikasi per role (dan per unit tim kerja).
 */
class UserGuide
{
    /**
     * Panduan yang relevan untuk pengguna, urut sesuai role. Admin program & super admin bisa membaca semua.
     *
     * @return list<string>
     */
    public function keysFor(User $user): array
    {
        if ($user->hasAnyRole([Role::SuperAdmin->value, Role::ProgramAdmin->value])) {
            $own = $user->isSuperAdmin() ? ['super_admin', 'program_admin'] : ['program_admin'];

            return array_values(array_unique([...$own, ...array_keys($this->guides())]));
        }

        $keys = [];
        $map = [
            Role::Leader->value => ['leader'],
            Role::Teacher->value => ['teacher'],
            Role::Staff->value => collect(StaffUnit::cases())->filter(fn (StaffUnit $unit) => $user->hasUnit($unit))->map(fn (StaffUnit $unit) => 'staff_'.$unit->value)->values()->all(),
            Role::Participant->value => ['participant'],
            Role::Guardian->value => ['guardian'],
        ];

        foreach ($map as $role => $roleKeys) {
            if ($user->hasRole($role)) {
                array_push($keys, ...$roleKeys);
            }
        }

        return $keys;
    }

    /**
     * @return array{title: string, icon: string, intro: string, topics: list<array{title: string, steps: list<string>}>}|null
     */
    public function get(string $key): ?array
    {
        return $this->guides()[$key] ?? null;
    }

    /**
     * @return array<string, array{title: string, icon: string, intro: string, topics: list<array{title: string, steps: list<string>}>}>
     */
    public function guides(): array
    {
        return [
            'participant' => [
                'title' => 'Peserta',
                'icon' => 'academic-cap',
                'intro' => 'Semua kegiatan harian ada di menu bawah: Beranda, Absen, Kelas, Asrama/Konsumsi, dan Menu.',
                'topics' => [
                    ['title' => 'Masuk pertama kali', 'steps' => [
                        'Login memakai No. HP, email, atau NIS beserta kata sandi awal dari panitia.',
                        'Ganti kata sandi awal, lalu setujui persetujuan penggunaan data pribadi.',
                        'Isi nama dan No. HP orang tua/wali (wajib), kemudian kamu masuk ke Beranda.',
                        'Lengkapi Biodata di menu profil (tempat & tanggal lahir, alamat, sekolah, golongan darah, kontak darurat).',
                        'Opsional: tambahkan email di Profil supaya bisa login dengan email.',
                    ]],
                    ['title' => 'Absen harian (wajah + lokasi)', 'steps' => [
                        'Buka Absen, izinkan akses kamera dan lokasi saat diminta browser.',
                        'Pertama kali, daftarkan wajah dengan mengikuti petunjuk di layar.',
                        'Pastikan berada di area kampus, lalu ambil foto dan kirim. Absen hanya bisa pada hari & jam yang ditentukan angkatan.',
                        'Absen sebelum batas tepat waktu mendapat poin. Bila ditolak, hubungi pengajar/pendamping.',
                    ]],
                    ['title' => 'Kelas: kode sesi, materi, tugas, quiz', 'steps' => [
                        'Saat sesi berlangsung, buka Input Kode Sesi lalu ketik 6 karakter dari pengajar atau pindai QR di layar.',
                        'Materi sesi ada di Materi; tugas di Tugas — kumpulkan sebelum tenggat (teks, berkas, foto, atau rekaman suara).',
                        'Kerjakan quiz di Quiz & Ujian. Perhatikan sisa waktu; jawaban terkumpul otomatis saat waktu habis.',
                        'Lihat nilai, grafik perkembangan, dan masukan pengajar di Nilai & Masukan.',
                    ]],
                    ['title' => 'Izin dan sakit', 'steps' => [
                        'Tidak bisa ikut kelas? Ajukan di Izin / Sakit beserta alasan dan lampiran (mis. surat dokter).',
                        'Merasa tidak enak badan di asrama: buka Lapor Sakit. Untuk keadaan darurat tekan tombol darurat agar petugas segera datang.',
                    ]],
                    ['title' => 'Konsumsi', 'steps' => [
                        'Saat makanan siap, kamu mendapat notifikasi jam dan tempat pengambilan.',
                        'Buka Konsumsi, pilih jadwal yang sedang dibuka, lalu tanda tangan di layar sebagai tanda terima. Satu kali per jadwal.',
                        'Tanda tangan sendiri menambah poin. Peserta yang tidak tinggal di asrama hanya menerima jenis konsumsi tertentu.',
                    ]],
                    ['title' => 'Asrama (bagi yang tinggal di asrama)', 'steps' => [
                        'Absen Asrama setiap malam pada jam yang ditentukan.',
                        'Izin Keluar diajukan beberapa jam sebelumnya; pulang/menginap perlu persetujuan wali. Tunjukkan QR izin ke petugas saat keluar dan kembali.',
                        'Sampaikan kerusakan atau kebutuhan kamar di Keluhan Asrama.',
                    ]],
                    ['title' => 'Poin, rating, dan survei', 'steps' => [
                        'Poin bertambah dari absen, kode sesi, tugas, quiz, tanda terima konsumsi, absen asrama, dan poin tambahan dari pengajar. Lihat di Poin & Lencana dan Peringkat.',
                        'Isi Rating Mingguan untuk vendor makanan dan pengajar, serta Survei yang dibagikan tim kerja.',
                        'Bagikan kode di Wali Saya supaya orang tua/wali bisa memantau perkembanganmu.',
                    ]],
                ],
            ],
            'guardian' => [
                'title' => 'Orang tua / wali',
                'icon' => 'user-group',
                'intro' => 'Pantau kehadiran, nilai, dan izin anak dari HP.',
                'topics' => [
                    ['title' => 'Mendaftar dan menghubungkan anak', 'steps' => [
                        'Daftar lewat tautan undangan dari panitia atau anak, lalu login dengan No. HP.',
                        'Buka Hubungkan Anak dan masukkan kode dari panitia atau dari menu Wali Saya milik anak.',
                        'Hubungan wali diverifikasi admin program sebelum data anak tampil.',
                    ]],
                    ['title' => 'Memantau anak', 'steps' => [
                        'Ringkasan Anak: kehadiran hari ini, nilai terbaru, dan catatan penting.',
                        'Kehadiran: riwayat absen harian dan sesi; Nilai & Catatan: penilaian mingguan/bulanan dan masukan pengajar.',
                        'Notifikasi dikirim saat anak tidak hadir, penilaian terbit, atau ada pengumuman.',
                    ]],
                    ['title' => 'Persetujuan izin', 'steps' => [
                        'Izin keluar asrama untuk pulang atau menginap menunggu persetujuan Anda di Persetujuan Izin.',
                        'Setujui atau tolak; petugas asrama baru bisa melepas anak setelah izin disetujui.',
                    ]],
                ],
            ],
            'teacher' => [
                'title' => 'Pengajar',
                'icon' => 'presentation-chart-bar',
                'intro' => 'Kelas bisa diajar beberapa pengajar; semua pengajar kelas punya akses yang sama.',
                'topics' => [
                    ['title' => 'Sesi belajar dan kehadiran', 'steps' => [
                        'Buka Sesi Belajar, jadwalkan sesi (bisa beberapa sekaligus), lalu Mulai sesi saat kelas dimulai.',
                        'Tampilkan kode kehadiran (mode proyektor) agar peserta memasukkannya di HP; kode berganti otomatis.',
                        'Tandai manual peserta yang hadir tanpa HP, izin, atau sakit, lalu Akhiri sesi.',
                        'Kehadiran mengajar Anda tercatat otomatis bila Anda membuat kode sesi dan ada peserta yang masuk. Rekap sesi & jam mengajar ada di Mengajar → Laporan Mengajar (bisa diunduh Excel).',
                        'Absen harian peserta yang perlu ditinjau ada di Pendampingan → Absen Harian.',
                    ]],
                    ['title' => 'Penilaian sesi dan periodik', 'steps' => [
                        'Di halaman sesi, tambahkan komponen penilaian (aspek, bobot, skor maksimal) atau pakai templat, lalu input skor peserta.',
                        'Tulis catatan perkembangan per peserta; pilih apakah wali boleh melihatnya.',
                        'Penilaian Periodik: susun nilai mingguan/bulanan per aspek (ada saran otomatis dari nilai sesi), lalu terbitkan.',
                    ]],
                    ['title' => 'Materi, tugas, dan quiz', 'steps' => [
                        'Unggah materi ke Bank Materi lalu tautkan ke sesi.',
                        'Buat Tugas dengan tenggat dan aturan terlambat, terbitkan, lalu nilai pengumpulan (bisa dengan umpan balik suara).',
                        'Susun soal di Bank Soal (bisa impor templat Excel), buat Quiz & Ujian, dan nilai jawaban esai di Perlu Dinilai.',
                    ]],
                    ['title' => 'Poin peserta', 'steps' => [
                        'Mengajar → Poin Peserta: pilih kelas, komponen yang sudah disetujui, peserta, dan (opsional) tugas/quiz terkait, lalu Beri poin.',
                        'Butuh komponen baru (mis. "Juara quiz kosakata")? Tekan Ajukan komponen; admin meninjau poinnya sebelum bisa dipakai.',
                    ]],
                    ['title' => 'Lainnya', 'steps' => [
                        'Tinjau pengajuan Izin / Sakit peserta kelas Anda.',
                        'Kirim pengumuman ke kelas lewat Kelola Pengumuman.',
                        'Lihat rating mingguan dari peserta di Rating Saya dan isi Survei yang ditujukan ke pengajar.',
                        'Bila akun Anda ditandai menerima konsumsi, tanda tangan penerimaan makan/snack di Konsumsi Saya, sama seperti peserta.',
                    ]],
                ],
            ],
            'staff_consumption' => [
                'title' => 'Tim konsumsi',
                'icon' => 'cake',
                'intro' => 'Tim konsumsi mencatat makanan yang datang; peserta menandatangani penerimaan di HP masing-masing.',
                'topics' => [
                    ['title' => 'Jadwal makan', 'steps' => [
                        'Konsumsi → Jadwal Makan: atur rencana per hari (vendor, menu, jam ambil, porsi). Salin ke minggu depan untuk makan utama.',
                        'Atur jenis konsumsi peserta tidak asrama dan tim pengajar/tim kerja penerima konsumsi lewat tombol Penerima konsumsi.',
                    ]],
                    ['title' => 'Input konsumsi saat makanan datang', 'steps' => [
                        'Buka Input Konsumsi, pilih Input pada sarapan/makan siang/makan malam hari itu.',
                        'Pilih vendor, isi jumlah (pcs/dos), menu (satu per baris), jam siap diambil, dan tempat makanan tersedia.',
                        'Ambil foto menu dan foto total makanan, lalu Simpan. Peserta diberi tahu tepat pada jam siap.',
                        'Snack: tambahkan Snack pagi/Snack sore hanya bila snack tersedia hari itu.',
                        'Salah input? Buka lagi dan Ubah (sampai 7 hari ke belakang).',
                    ]],
                    ['title' => 'Monitor dan penerimaan diwakilkan', 'steps' => [
                        'Monitor Makan menampilkan porsi diterima vs total dan daftar penerima yang belum mengambil (yang izin/sakit ditandai; tim pengajar/kerja diberi label).',
                        'Untuk peserta sakit atau di kamar, catat penerimaan diwakilkan dengan tanda tangan petugas dan catatan.',
                        'Teruskan daftar Alergi & Pantangan ke vendor; pantau Rekap Rating Vendor tiap minggu.',
                    ]],
                    ['title' => 'Laporan', 'steps' => [
                        'Laporan → Pusat Laporan: penerimaan konsumsi dan input konsumsi harian, mingguan, atau bulanan, lengkap dengan total per vendor; unduh Excel sebagai dasar pembayaran vendor.',
                    ]],
                ],
            ],
            'staff_dormitory' => [
                'title' => 'Pengelola asrama',
                'icon' => 'home-modern',
                'intro' => 'Kelola kehadiran malam, izin keluar, dan kebutuhan kamar peserta asrama.',
                'topics' => [
                    ['title' => 'Absen malam', 'steps' => [
                        'Peserta absen sendiri pada jendela waktu malam; konfirmasi atau tandai manual di Absen Malam.',
                        'Bila Konfirmasi otomatis absen asrama dinyalakan di Pengaturan Sistem, absen mandiri yang lolos verifikasi langsung terkonfirmasi (bertanda "otomatis"); Anda tinggal menangani yang ditolak atau belum absen.',
                        'Peserta yang tidak hadir ditandai; wali bisa diberi tahu otomatis (sesuai pengaturan).',
                    ]],
                    ['title' => 'Izin keluar', 'steps' => [
                        'Setujui atau tolak pengajuan di Izin Keluar; izin pulang/menginap menunggu persetujuan wali terlebih dahulu.',
                        'Pindai QR Izin saat peserta keluar dan saat kembali; keterlambatan tercatat otomatis.',
                    ]],
                    ['title' => 'Keluhan, inventaris, dan kesehatan', 'steps' => [
                        'Tangani Keluhan & Permintaan sampai selesai agar peserta mendapat kabar.',
                        'Catat kondisi barang per kamar di Inventaris Kamar.',
                        'Laporan Sakit dari peserta asrama tampil di menu Kesehatan untuk ditindaklanjuti.',
                    ]],
                ],
            ],
            'staff_health' => [
                'title' => 'Petugas kesehatan',
                'icon' => 'heart',
                'intro' => 'Tindak lanjuti laporan sakit peserta dengan cepat.',
                'topics' => [
                    ['title' => 'Menangani laporan sakit', 'steps' => [
                        'Laporan baru dan tombol darurat dikirim sebagai notifikasi; buka Laporan Sakit.',
                        'Catat tindakan (periksa, obat, rujuk, istirahat) dan ubah status sampai selesai.',
                        'Bila perlu, ajukan makanan khusus; tim konsumsi melihatnya di Monitor Makan.',
                        'Data kesehatan bersifat rahasia dan hanya terlihat oleh petugas yang berwenang.',
                    ]],
                ],
            ],
            'program_admin' => [
                'title' => 'Admin program',
                'icon' => 'clipboard-document-list',
                'intro' => 'Menyiapkan data program, mengatur peserta, dan memantau jalannya kegiatan.',
                'topics' => [
                    ['title' => 'Menyiapkan program', 'steps' => [
                        'Angkatan: tanggal program, target kehadiran, nilai lulus, serta hari & jam absen harian.',
                        'Lokasi: titik dan radius kampus/asrama untuk validasi absen. Bahasa dan Asrama & Kamar sesuai kebutuhan.',
                        'Kelas: pilih angkatan, bahasa, pengajar utama, dan pengajar lain.',
                        'Pengguna & Tim: buat akun pengajar, tim kerja (pilih unit), dan pimpinan; satu akun boleh punya beberapa role. Aktifkan Menerima konsumsi untuk tim yang ikut makan bersama peserta.',
                    ]],
                    ['title' => 'Peserta', 'steps' => [
                        'Impor peserta dari templat Excel atau tambah satu per satu (wajib kontak orang tua/wali).',
                        'Centang banyak peserta untuk memindahkan/membagi rata kelas, mengisi kamar otomatis, menandai tidak asrama, atau reset kata sandi massal.',
                        'Verifikasi hubungan wali di menu Wali.',
                    ]],
                    ['title' => 'Kegiatan dan poin', 'steps' => [
                        'Kelola Pengumuman untuk semua, per role, kelas, atau asrama.',
                        'Survei & Evaluasi: pakai templat sarana prasarana, pilih angkatan & responden, tentukan tenggat, lalu lihat hasilnya.',
                        'Komponen Poin: ubah poin aturan otomatis, tambah komponen, dan setujui/tolak pengajuan pengajar. Level & lencana di Gamifikasi.',
                    ]],
                    ['title' => 'Memantau dan melapor', 'steps' => [
                        'Dashboard Eksekutif: grafik kehadiran, nilai, dan konsumsi dengan filter angkatan, kelas, dan rentang tanggal, serta daftar peserta yang perlu perhatian.',
                        'Pusat Laporan: absen harian, absen per sesi, konsumsi, input konsumsi, rekap nilai, dan laporan mengajar tiap pengajar; unduh Excel.',
                    ]],
                ],
            ],
            'leader' => [
                'title' => 'Pimpinan',
                'icon' => 'presentation-chart-line',
                'intro' => 'Akses baca saja untuk memantau program dan mengambil keputusan.',
                'topics' => [
                    ['title' => 'Dashboard eksekutif', 'steps' => [
                        'Buka Dashboard Eksekutif; atur angkatan, kelas, dan rentang tanggal di bagian atas.',
                        'Baca ringkasan angka utama, tren kehadiran harian, sebaran status, rata-rata nilai per kelas, dan serapan konsumsi.',
                        'Bagian Perlu perhatian menampilkan peserta dengan kehadiran di bawah target atau nilai di bawah batas lulus.',
                        'Pilih satu peserta untuk melihat grafik kehadiran dan nilainya per minggu.',
                    ]],
                    ['title' => 'Laporan', 'steps' => [
                        'Pusat Laporan: pilih jenis laporan dan periode (harian, mingguan, bulanan), lalu Unduh Excel.',
                        'Rekap Rating Pengajar dan hasil Survei & Evaluasi juga bisa dibuka.',
                    ]],
                ],
            ],
            'super_admin' => [
                'title' => 'Super admin',
                'icon' => 'shield-check',
                'intro' => 'Mengelola pengaturan sistem dan hak akses; memiliki semua akses admin program.',
                'topics' => [
                    ['title' => 'Sistem', 'steps' => [
                        'Pengaturan Sistem: radius & ambang verifikasi wajah, jam bawaan absen, aturan asrama (termasuk Konfirmasi otomatis absen asrama), batas unggah, dan predikat nilai.',
                        'Role & Hak Akses: atur izin tiap role. Perubahan berlaku langsung untuk semua pengguna role tersebut.',
                        'Log Audit: telusuri siapa mengubah apa dan kapan.',
                    ]],
                ],
            ],
        ];
    }
}
