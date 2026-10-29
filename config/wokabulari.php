<?php

return [

    /*
    | Versi aplikasi (semver). Naikkan setiap rilis dan catat di docs/CHANGELOG.md:
    | patch (1.0.x) untuk perbaikan, minor (1.x.0) untuk fitur baru, major untuk perubahan besar.
    */
    'version' => '1.12.0',

    /*
    |--------------------------------------------------------------------------
    | Verifikasi wajah & geofence
    |--------------------------------------------------------------------------
    | Boleh dimatikan di lokal untuk mempercepat uji. WAJIB true di produksi.
    */
    'face_verification_enabled' => (bool) env('FACE_VERIFICATION_ENABLED', true),
    'geofence_enabled' => (bool) env('GEOFENCE_ENABLED', true),

    /*
    | Versi teks persetujuan data pribadi (UU No. 27 Tahun 2022). Naikkan versi
    | bila teks berubah agar peserta & wali menyetujui ulang.
    */
    'consent_version' => '1.0',

    /*
    | Kata sandi awal untuk akun peserta hasil import. Peserta wajib menggantinya
    | saat login pertama. Isi lewat .env, jangan ditulis di repo.
    */
    'participant_default_password' => env('PARTICIPANT_DEFAULT_PASSWORD'),

    /*
    | Konsumsi bawaan untuk peserta tidak asrama; diubah tim konsumsi di halaman Jadwal makan
    | (tersimpan di tabel settings dengan kunci meals.non_resident_types).
    */
    'meals' => [
        'non_resident_types' => ['morning_snack', 'lunch', 'afternoon_snack'],
        // Tim pengajar/tim kerja yang ditandai menerima konsumsi (diatur tim konsumsi).
        'staff_types' => ['morning_snack', 'lunch', 'afternoon_snack'],
    ],

    /*
    | Super admin untuk TrialSeeder (uji coba localhost/staging). Isi lewat .env.
    */
    'trial_admin' => [
        'username' => env('TRIAL_ADMIN_USERNAME', 'admin'),
        'password' => env('TRIAL_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nilai bawaan pengaturan sistem (tabel settings)
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'attendance.default_radius_m' => 150,
        'attendance.face_threshold' => 0.5,
        'attendance.max_gps_accuracy_m' => 100,
        'attendance.late_after' => '07:30',
        'attendance.open_from' => '05:00',
        'session.code_duration_minutes' => 15,
        'session.validate_location' => false,
        'dorm.check_window_start' => '20:00',
        'dorm.check_window_end' => '21:30',
        'dorm.curfew' => '22:00',
        'dorm.notify_guardian_absent' => true,
        // Konfirmasi otomatis absen asrama mandiri yang lolos verifikasi (mati = dikonfirmasi pengelola asrama).
        'dorm.auto_confirm' => false,
        'exit.max_per_week' => 2,
        'exit.min_hours_before' => 3,
        'exit.guardian_required_types' => ['home_visit', 'overnight'],
        'files.max_upload_mb' => 50,
        'grade.threshold_a' => 85,
        'grade.threshold_b' => 75,
        'grade.threshold_c' => 65,
        'retention.selfie_days_after_batch' => 90,
        'leader.weekly_email' => true,
        'program.name' => 'Kelas Bahasa SMK Go Global',
        'program.organizer' => 'Poltekkes Kemenkes Manado',
        'program.dorm_contact' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission granular per role (SPEC 2)
    |--------------------------------------------------------------------------
    | Role pimpinan selalu read-only. Super admin mendapat semua permission.
    */
    'permissions' => [
        'user.manage', 'role.manage', 'setting.manage', 'audit.view',
        'batch.manage', 'classroom.manage', 'participant.manage', 'participant.view',
        'location.manage', 'dormitory.manage', 'vendor.manage', 'guardian.verify',
        'attendance.approve', 'attendance.view-all', 'attendance.confirm-dorm',
        'session.manage', 'session.view-all', 'material.manage',
        'assessment.manage', 'assignment.manage', 'quiz.manage',
        'leave.approve', 'word.manage',
        'meal.manage', 'meal.monitor', 'meal.proxy',
        'rating.view-vendor', 'rating.view-teacher', 'rating.view-names',
        'dorm-exit.approve', 'dorm-request.handle', 'dorm-inventory.manage',
        'health.view-detail', 'health.handle', 'health.view-stats',
        'gamification.manage', 'point.award', 'evaluation.manage', 'evaluation.results',
        'announcement.manage', 'certificate.manage',
        'report.view', 'report.export', 'report.health-detail',
        'dashboard.leader',
    ],

    'role_permissions' => [
        'leader' => [
            'participant.view', 'attendance.view-all', 'session.view-all',
            'rating.view-vendor', 'rating.view-teacher', 'rating.view-names',
            'health.view-stats', 'evaluation.results',
            'report.view', 'report.export', 'dashboard.leader',
        ],
        'program_admin' => [
            'batch.manage', 'classroom.manage', 'participant.manage', 'participant.view',
            'location.manage', 'dormitory.manage', 'guardian.verify',
            'attendance.approve', 'attendance.view-all', 'session.manage', 'session.view-all',
            'material.manage', 'leave.approve', 'word.manage',
            'rating.view-vendor', 'rating.view-teacher', 'rating.view-names',
            'gamification.manage', 'point.award', 'evaluation.manage', 'evaluation.results',
            'announcement.manage', 'certificate.manage',
            'report.view', 'report.export', 'health.view-stats', 'dashboard.leader',
            // Ikut menyetujui izin keluar asrama bersama pengelola asrama.
            'dorm-exit.approve',
        ],
        'teacher' => [
            'participant.view', 'attendance.approve', 'session.manage', 'material.manage',
            'assessment.manage', 'assignment.manage', 'quiz.manage',
            'leave.approve', 'word.manage', 'rating.view-teacher', 'announcement.manage', 'point.award',
        ],
        'staff' => [],
        'participant' => [],
        'guardian' => [],
    ],

    /*
    | Permission tambahan untuk tim kerja sesuai unit (staff_units).
    */
    'unit_permissions' => [
        'consumption' => ['meal.manage', 'meal.monitor', 'meal.proxy', 'vendor.manage', 'rating.view-vendor', 'report.view', 'report.export', 'announcement.manage'],
        'dormitory' => ['attendance.confirm-dorm', 'dorm-exit.approve', 'dorm-request.handle', 'dorm-inventory.manage', 'health.view-detail', 'health.handle', 'report.view', 'report.export', 'report.health-detail', 'announcement.manage'],
        'health' => ['health.view-detail', 'health.handle', 'report.view', 'report.health-detail', 'announcement.manage'],
    ],
];
