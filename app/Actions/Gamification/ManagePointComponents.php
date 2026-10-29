<?php

namespace App\Actions\Gamification;

use App\Enums\PointCategory;
use App\Enums\ReviewStatus;
use App\Models\GamificationRule;
use App\Models\User;
use App\Notifications\PointComponentProposedNotification;
use App\Notifications\PointComponentReviewedNotification;
use App\Services\Gamification\PointService;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Master data Komponen Poin: ubah aturan otomatis, tambah komponen tambahan, tinjau pengajuan pengajar.
 */
class ManagePointComponents
{
    public function __construct(protected PointService $points) {}

    /**
     * Komponen tambahan oleh admin (langsung disetujui).
     *
     * @param  array{name: string, category: string, points: int|string, max_per_day?: int|string|null, description?: ?string, is_active?: bool}  $data
     */
    public function create(array $data, User $by): GamificationRule
    {
        $component = GamificationRule::query()->create($this->attributes($data) + [
            'is_custom' => true,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'status' => ReviewStatus::Approved,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ]);

        activity('gamifikasi')->causedBy($by)->performedOn($component)->log('Menambah komponen poin');

        return $component;
    }

    /**
     * Aturan otomatis: hanya poin, batas per hari, dan status aktif. Komponen tambahan: semua isian.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(GamificationRule $component, array $data): GamificationRule
    {
        $attributes = $component->is_custom
            ? $this->attributes($data)
            : ['points' => (int) $data['points'], 'max_per_day' => filled($data['max_per_day'] ?? null) ? (int) $data['max_per_day'] : null];

        $component->update($attributes + ['is_active' => (bool) ($data['is_active'] ?? $component->is_active)]);
        $this->points->flushRules();

        return $component;
    }

    /**
     * Pengajuan pengajar: menunggu persetujuan pengelola gamifikasi.
     *
     * @param  array{name: string, category: string, points: int|string, description?: ?string}  $data
     */
    public function propose(array $data, User $teacher): GamificationRule
    {
        if (! in_array(PointCategory::from($data['category']), PointCategory::forCustom(), true)) {
            throw new InvalidArgumentException('Kategori ini tidak bisa diajukan.');
        }

        $component = GamificationRule::query()->create($this->attributes($data) + [
            'is_custom' => true,
            'is_active' => true,
            'status' => ReviewStatus::Pending,
            'proposed_by' => $teacher->id,
        ]);

        $reviewers = User::query()->active()->permission('gamification.manage')->whereKeyNot($teacher->id)->get();
        Notification::send($reviewers, new PointComponentProposedNotification($component->load('proposer')));

        return $component;
    }

    public function approve(GamificationRule $component, User $by, ?int $points = null): GamificationRule
    {
        return $this->review($component, $by, ReviewStatus::Approved, null, $points);
    }

    public function reject(GamificationRule $component, User $by, string $note): GamificationRule
    {
        return $this->review($component, $by, ReviewStatus::Rejected, $note);
    }

    /**
     * Hapus komponen tambahan. Yang sudah pernah diberikan hanya dinonaktifkan agar riwayat poin tetap utuh.
     *
     * @return bool true bila terhapus, false bila dinonaktifkan
     */
    public function delete(GamificationRule $component): bool
    {
        if (! $component->is_custom) {
            throw new InvalidArgumentException('Aturan otomatis tidak bisa dihapus, cukup dinonaktifkan.');
        }

        if ($component->awards()->exists()) {
            $component->update(['is_active' => false]);

            return false;
        }

        $component->delete();

        return true;
    }

    protected function review(GamificationRule $component, User $by, ReviewStatus $status, ?string $note = null, ?int $points = null): GamificationRule
    {
        if ($component->status !== ReviewStatus::Pending) {
            throw new InvalidArgumentException('Pengajuan ini sudah ditinjau.');
        }

        $component->update([
            'status' => $status,
            'points' => $points ?? $component->points,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'review_note' => filled($note) ? trim($note) : null,
        ]);

        activity('gamifikasi')->causedBy($by)->performedOn($component)
            ->withProperties(['status' => $status->value, 'catatan' => $note])->log('Meninjau pengajuan komponen poin');

        $component->proposer?->notify(new PointComponentReviewedNotification($component));

        return $component;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'category' => PointCategory::from($data['category']),
            'points' => (int) $data['points'],
            'max_per_day' => filled($data['max_per_day'] ?? null) ? (int) $data['max_per_day'] : null,
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ];
    }
}
