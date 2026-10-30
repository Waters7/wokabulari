<?php

namespace App\Policies;

use App\Models\LearningSession;
use App\Models\SessionDocumentation;
use App\Models\User;

/**
 * Dokumentasi kelas: dilihat pengajar kelas & yang boleh melihat semua sesi (admin, pimpinan); bukan peserta.
 */
class SessionDocumentationPolicy
{
    public function viewAny(User $user, LearningSession $session): bool
    {
        return $user->can('session.view-all') || $user->can('update', $session) || $session->classroom->hasTeacher($user);
    }

    public function view(User $user, SessionDocumentation $documentation): bool
    {
        return $this->viewAny($user, $documentation->session);
    }

    public function create(User $user, LearningSession $session): bool
    {
        return $user->can('update', $session);
    }

    /** Hapus hanya bila masih ada dokumentasi lain pada tahap yang sama (bukti tidak hilang). */
    public function delete(User $user, SessionDocumentation $documentation): bool
    {
        return $user->can('update', $documentation->session)
            && $documentation->session->documentations()->where('phase', $documentation->phase)->count() > 1;
    }
}
