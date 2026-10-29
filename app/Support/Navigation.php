<?php

namespace App\Support;

use App\Enums\Role;
use App\Enums\StaffUnit;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Definisi menu per role. Item hanya tampil jika route-nya terdaftar dan pengguna berhak.
 */
class Navigation
{
    /**
     * Grup menu sidebar/drawer.
     *
     * @return list<array{heading: string, items: list<array<string, mixed>>}>
     */
    public function sidebar(User $user): array
    {
        $groups = [];

        foreach ($this->definitions() as $heading => $items) {
            $visible = collect($items)
                ->filter(fn (array $item) => $this->visible($user, $item))
                ->map(fn (array $item) => $this->resolve($item))
                ->values()
                ->all();

            if ($visible !== []) {
                $groups[] = ['heading' => $heading, 'items' => $visible];
            }
        }

        return $groups;
    }

    /**
     * Item bottom navigation (maks. 5) untuk peserta, wali, dan tim kerja.
     *
     * @return list<array<string, mixed>>
     */
    public function bottom(User $user): array
    {
        $role = $user->primaryRole();

        if (! $role?->usesBottomNav()) {
            return [];
        }

        $routes = match ($role) {
            Role::Participant => ['dashboard', 'attendance.daily', 'participant.class', 'participant.dorm'],
            Role::Guardian => ['dashboard', 'guardian.child', 'guardian.attendance', 'guardian.approvals'],
            Role::Staff => array_merge(['dashboard'], $this->staffBottomRoutes($user)),
            default => [],
        };

        $all = collect($this->definitions())->flatten(1)->keyBy('route');

        $items = collect($routes)
            ->map(fn (string $route) => $all->get($route) ?? $this->extraBottomItems()[$route] ?? null)
            // Peserta tidak asrama: tab "Asrama" menjadi "Konsumsi".
            ->map(fn (?array $item) => $item && $item['route'] === 'participant.dorm' && ! $user->livesInDorm()
                ? ['label' => 'Konsumsi', 'icon' => 'cake'] + $item
                : $item)
            ->filter(fn (?array $item) => $item !== null && $this->visible($user, $item))
            ->map(fn (array $item) => $this->resolve($item, short: true))
            ->take(4)
            ->values()
            ->all();

        $items[] = ['label' => 'Menu', 'icon' => 'squares-2x2', 'action' => 'menu', 'active' => false, 'url' => null];

        return $items;
    }

    /** @return list<string> */
    protected function staffBottomRoutes(User $user): array
    {
        $routes = [];

        if ($user->hasUnit(StaffUnit::Consumption)) {
            array_push($routes, 'meals.deliveries.index', 'meals.monitor');
        }

        if ($user->hasUnit(StaffUnit::Dormitory)) {
            array_push($routes, 'dorm.manage.attendance', 'dorm.manage.exits');
        }

        if ($user->hasUnit(StaffUnit::Health) || $user->hasUnit(StaffUnit::Dormitory)) {
            $routes[] = 'health.index';
        }

        return array_slice(array_values(array_unique($routes)), 0, 3);
    }

    /** @return array<string, array<string, mixed>> */
    protected function extraBottomItems(): array
    {
        return [
            'participant.class' => ['label' => 'Kelas', 'icon' => 'academic-cap', 'route' => 'participant.class', 'match' => ['participant.class', 'materials.*', 'assignments.*', 'quizzes.*', 'grades.*', 'sessions.code', 'leave.*'], 'roles' => [Role::Participant]],
            'participant.dorm' => ['label' => 'Asrama', 'icon' => 'home-modern', 'route' => 'participant.dorm', 'match' => ['participant.dorm', 'dorm.attendance', 'dorm.exit.*', 'dorm.requests.*', 'health.report*', 'meals.index'], 'roles' => [Role::Participant]],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function visible(User $user, array $item): bool
    {
        if (! Route::has($item['route'])) {
            return false;
        }

        if (isset($item['roles']) && ! $user->hasAnyRole(array_map(fn (Role $role) => $role->value, $item['roles']))) {
            return false;
        }

        if (isset($item['units']) && ! collect($item['units'])->contains(fn (StaffUnit $unit) => $user->hasUnit($unit))) {
            return false;
        }

        // 'can' boleh daftar permission: cukup salah satu.
        if (isset($item['can']) && ! collect((array) $item['can'])->contains(fn (string $permission) => $user->can($permission))) {
            return false;
        }

        // Menu asrama hanya untuk peserta yang tinggal di asrama.
        if (($item['resident'] ?? false) && ! $user->livesInDorm()) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function resolve(array $item, bool $short = false): array
    {
        return [
            'label' => $short ? ($item['short'] ?? $item['label']) : $item['label'],
            'icon' => $item['icon'],
            'url' => route($item['route']),
            'route' => $item['route'],
            'active' => request()->routeIs(...($item['match'] ?? [$item['route']])),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    protected function definitions(): array
    {
        $participant = [Role::Participant];
        $teacher = [Role::Teacher];

        return [
            'Utama' => [
                ['label' => 'Beranda', 'icon' => 'home', 'route' => 'dashboard'],
                ['label' => 'Dashboard Pimpinan', 'short' => 'Pimpinan', 'icon' => 'presentation-chart-line', 'route' => 'leader.dashboard', 'match' => ['leader.*'], 'can' => 'dashboard.leader'],
                ['label' => 'Pengumuman', 'icon' => 'megaphone', 'route' => 'announcements.index', 'match' => ['announcements.*']],
            ],
            'Kelas Saya' => [
                ['label' => 'Absen Harian', 'short' => 'Absen', 'icon' => 'camera', 'route' => 'attendance.daily', 'match' => ['attendance.daily', 'attendance.history'], 'roles' => $participant],
                ['label' => 'Input Kode Sesi', 'icon' => 'qr-code', 'route' => 'sessions.code', 'roles' => $participant],
                ['label' => 'Materi', 'icon' => 'book-open', 'route' => 'materials.index', 'match' => ['materials.*'], 'roles' => $participant],
                ['label' => 'Tugas', 'icon' => 'clipboard-document-check', 'route' => 'assignments.index', 'match' => ['assignments.*'], 'roles' => $participant],
                ['label' => 'Quiz & Ujian', 'icon' => 'pencil-square', 'route' => 'quizzes.index', 'match' => ['quizzes.*'], 'roles' => $participant],
                ['label' => 'Nilai & Masukan', 'icon' => 'chart-bar', 'route' => 'grades.index', 'match' => ['grades.*'], 'roles' => $participant],
                ['label' => 'Izin / Sakit', 'icon' => 'document-text', 'route' => 'leave.index', 'match' => ['leave.*'], 'roles' => $participant],
            ],
            'Asrama & Konsumsi' => [
                ['label' => 'Konsumsi', 'icon' => 'cake', 'route' => 'meals.index', 'roles' => $participant],
                ['label' => 'Absen Asrama', 'icon' => 'moon', 'route' => 'dorm.attendance', 'roles' => $participant, 'resident' => true],
                ['label' => 'Izin Keluar', 'icon' => 'arrow-right-start-on-rectangle', 'route' => 'dorm.exit.index', 'match' => ['dorm.exit.*'], 'roles' => $participant, 'resident' => true],
                ['label' => 'Keluhan Asrama', 'icon' => 'wrench-screwdriver', 'route' => 'dorm.requests.index', 'match' => ['dorm.requests.*'], 'roles' => $participant, 'resident' => true],
                ['label' => 'Lapor Sakit', 'icon' => 'heart', 'route' => 'health.report', 'match' => ['health.report*'], 'roles' => $participant],
            ],
            'Aktivitas' => [
                ['label' => 'Rating Mingguan', 'icon' => 'star', 'route' => 'ratings.index', 'match' => ['ratings.*'], 'roles' => $participant],
                ['label' => 'Survei', 'icon' => 'clipboard-document-list', 'route' => 'evaluations.index', 'match' => ['evaluations.*'], 'roles' => [Role::Participant, Role::Teacher]],
                ['label' => 'Peringkat', 'icon' => 'trophy', 'route' => 'leaderboard', 'roles' => [Role::Participant, Role::Teacher, Role::ProgramAdmin, Role::Leader, Role::SuperAdmin]],
                ['label' => 'Poin & Lencana', 'icon' => 'sparkles', 'route' => 'points.index', 'roles' => $participant],
                ['label' => 'Sertifikat', 'icon' => 'academic-cap', 'route' => 'certificates.mine', 'roles' => $participant],
                ['label' => 'Wali Saya', 'icon' => 'user-group', 'route' => 'participant.guardians', 'roles' => $participant],
            ],
            'Anak Saya' => [
                ['label' => 'Ringkasan Anak', 'short' => 'Anak', 'icon' => 'user', 'route' => 'guardian.child', 'roles' => [Role::Guardian]],
                ['label' => 'Kehadiran', 'icon' => 'calendar-days', 'route' => 'guardian.attendance', 'roles' => [Role::Guardian]],
                ['label' => 'Nilai & Catatan', 'icon' => 'chart-bar', 'route' => 'guardian.grades', 'roles' => [Role::Guardian]],
                ['label' => 'Persetujuan Izin', 'short' => 'Izin', 'icon' => 'check-badge', 'route' => 'guardian.approvals', 'roles' => [Role::Guardian]],
                ['label' => 'Hubungkan Anak', 'icon' => 'link', 'route' => 'guardian.link', 'roles' => [Role::Guardian]],
            ],
            'Mengajar' => [
                ['label' => 'Sesi Belajar', 'icon' => 'calendar', 'route' => 'teach.sessions.index', 'match' => ['teach.sessions.*'], 'can' => 'session.manage'],
                ['label' => 'Bank Materi', 'icon' => 'folder-open', 'route' => 'teach.materials.bank', 'can' => 'material.manage'],
                ['label' => 'Tugas', 'icon' => 'clipboard-document-check', 'route' => 'teach.assignments.index', 'match' => ['teach.assignments.*'], 'can' => 'assignment.manage'],
                ['label' => 'Quiz & Ujian', 'icon' => 'pencil-square', 'route' => 'teach.quizzes.index', 'match' => ['teach.quizzes.*'], 'can' => 'quiz.manage'],
                ['label' => 'Bank Soal', 'icon' => 'rectangle-stack', 'route' => 'teach.questions.index', 'match' => ['teach.questions.*'], 'can' => 'quiz.manage'],
                ['label' => 'Perlu Dinilai', 'icon' => 'inbox-stack', 'route' => 'teach.grading', 'can' => 'quiz.manage'],
                ['label' => 'Penilaian Periodik', 'icon' => 'document-chart-bar', 'route' => 'teach.assessments.index', 'match' => ['teach.assessments.*'], 'can' => 'assessment.manage'],
                ['label' => 'Rating Saya', 'icon' => 'star', 'route' => 'teach.ratings', 'roles' => $teacher],
            ],
            'Pendampingan' => [
                ['label' => 'Absen Harian', 'icon' => 'camera', 'route' => 'manage.attendance.index', 'can' => 'attendance.approve'],
                ['label' => 'Izin / Sakit Peserta', 'icon' => 'document-check', 'route' => 'manage.leave.index', 'can' => 'leave.approve'],
                ['label' => 'Kata Hari Ini', 'icon' => 'language', 'route' => 'manage.words.index', 'can' => 'word.manage'],
            ],
            'Konsumsi' => [
                ['label' => 'Input Konsumsi', 'short' => 'Input', 'icon' => 'truck', 'route' => 'meals.deliveries.index', 'match' => ['meals.deliveries.*'], 'can' => 'meal.manage'],
                ['label' => 'Monitor Makan', 'short' => 'Monitor', 'icon' => 'signal', 'route' => 'meals.monitor', 'can' => 'meal.monitor'],
                ['label' => 'Jadwal Makan', 'short' => 'Jadwal', 'icon' => 'calendar-days', 'route' => 'meals.schedules', 'can' => 'meal.manage'],
                ['label' => 'Alergi & Pantangan', 'icon' => 'exclamation-triangle', 'route' => 'meals.allergies', 'can' => 'meal.monitor'],
                ['label' => 'Rekap Rating Vendor', 'icon' => 'star', 'route' => 'meals.ratings', 'can' => 'rating.view-vendor'],
                ['label' => 'Vendor', 'icon' => 'building-storefront', 'route' => 'master.vendors', 'can' => 'vendor.manage'],
            ],
            'Asrama' => [
                ['label' => 'Absen Malam', 'short' => 'Absen Malam', 'icon' => 'moon', 'route' => 'dorm.manage.attendance', 'can' => 'attendance.confirm-dorm'],
                ['label' => 'Izin Keluar', 'short' => 'Izin Keluar', 'icon' => 'arrow-right-start-on-rectangle', 'route' => 'dorm.manage.exits', 'match' => ['dorm.manage.exits*'], 'can' => 'dorm-exit.approve'],
                ['label' => 'Pindai QR Izin', 'icon' => 'qr-code', 'route' => 'dorm.manage.scan', 'can' => 'dorm-exit.approve'],
                ['label' => 'Keluhan & Permintaan', 'icon' => 'wrench-screwdriver', 'route' => 'dorm.manage.requests', 'can' => 'dorm-request.handle'],
                ['label' => 'Inventaris Kamar', 'icon' => 'archive-box', 'route' => 'dorm.manage.inventory', 'can' => 'dorm-inventory.manage'],
            ],
            'Kesehatan' => [
                ['label' => 'Laporan Sakit', 'short' => 'Sakit', 'icon' => 'heart', 'route' => 'health.index', 'match' => ['health.index', 'health.show'], 'can' => 'health.view-detail'],
            ],
            'Program' => [
                ['label' => 'Angkatan', 'icon' => 'flag', 'route' => 'master.batches', 'can' => 'batch.manage'],
                ['label' => 'Kelas', 'icon' => 'building-library', 'route' => 'master.classrooms', 'match' => ['master.classrooms*'], 'can' => 'classroom.manage'],
                ['label' => 'Peserta', 'icon' => 'user-group', 'route' => 'master.participants', 'match' => ['master.participants*'], 'can' => 'participant.view'],
                ['label' => 'Pengguna & Tim', 'icon' => 'users', 'route' => 'master.users', 'can' => 'user.manage'],
                ['label' => 'Wali', 'icon' => 'user-plus', 'route' => 'master.guardians', 'can' => 'guardian.verify'],
                ['label' => 'Lokasi', 'icon' => 'map-pin', 'route' => 'master.locations', 'can' => 'location.manage'],
                ['label' => 'Asrama & Kamar', 'icon' => 'home-modern', 'route' => 'master.dormitories', 'match' => ['master.dormitories*'], 'can' => 'dormitory.manage'],
                ['label' => 'Bahasa', 'icon' => 'language', 'route' => 'master.languages', 'can' => 'batch.manage'],
            ],
            'Kegiatan Program' => [
                ['label' => 'Kelola Pengumuman', 'icon' => 'megaphone', 'route' => 'manage.announcements', 'can' => 'announcement.manage'],
                ['label' => 'Survei & Evaluasi', 'icon' => 'clipboard-document-list', 'route' => 'manage.evaluations.index', 'match' => ['manage.evaluations.*'], 'can' => ['evaluation.manage', 'evaluation.results']],
                ['label' => 'Sertifikat', 'icon' => 'academic-cap', 'route' => 'manage.certificates', 'can' => 'certificate.manage'],
                ['label' => 'Gamifikasi', 'icon' => 'sparkles', 'route' => 'manage.gamification', 'can' => 'gamification.manage'],
            ],
            'Laporan' => [
                ['label' => 'Pusat Laporan', 'icon' => 'document-arrow-down', 'route' => 'reports.index', 'match' => ['reports.*'], 'can' => 'report.view'],
                ['label' => 'Rekap Rating Pengajar', 'icon' => 'star', 'route' => 'manage.teacher-ratings', 'can' => 'rating.view-names'],
                ['label' => 'Profil Peserta 360°', 'icon' => 'identification', 'route' => 'leader.participants', 'match' => ['leader.participants', 'leader.participant'], 'can' => 'dashboard.leader'],
            ],
            'Sistem' => [
                ['label' => 'Pengaturan Sistem', 'icon' => 'cog-6-tooth', 'route' => 'system.settings', 'can' => 'setting.manage'],
                ['label' => 'Role & Hak Akses', 'icon' => 'shield-check', 'route' => 'system.roles', 'can' => 'role.manage'],
                ['label' => 'Log Audit', 'icon' => 'finger-print', 'route' => 'system.audit', 'can' => 'audit.view'],
            ],
        ];
    }
}
