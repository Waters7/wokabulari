<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementSource;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Classroom;
use App\Models\Dormitory;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Pengumuman bersasaran dari Tim Kerja, Pengajar, atau Asrama (SPEC 3.22, 3.17).
 */
class ManageAnnouncements
{
    /**
     * @param  array<string, mixed>  $data  title, body, source, audience, target_id, target_role, is_pinned, notify_whatsapp,
     *                                      publish (now | schedule | draft), publish_at
     */
    public function save(array $data, User $by, ?Announcement $announcement = null): Announcement
    {
        $source = AnnouncementSource::from($data['source']);
        $audience = AnnouncementAudience::from($data['audience']);

        if (! in_array($source, AnnouncementSource::allowedFor($by), true)) {
            throw new InvalidArgumentException('Ngana tidak bisa mengumumkan atas nama '.$source->label().'.');
        }

        if (! in_array($audience, AnnouncementAudience::forSource($source), true)) {
            throw new InvalidArgumentException('Sasaran '.$audience->label().' tidak tersedia untuk pengumuman '.$source->label().'.');
        }

        $targetId = null;
        $targetRole = null;

        if ($audience === AnnouncementAudience::Classroom) {
            $classroom = Classroom::query()->find($data['target_id'] ?? null) ?? throw new InvalidArgumentException('Pilih kelas sasaran.');
            if ($source === AnnouncementSource::Teacher && ! $classroom->hasTeacher($by) && ! $by->isSuperAdmin()) {
                throw new InvalidArgumentException('Pengajar hanya bisa mengumumkan ke kelas yang diajarnya.');
            }
            $targetId = $classroom->id;
        } elseif ($audience === AnnouncementAudience::Dormitory) {
            $targetId = Dormitory::query()->find($data['target_id'] ?? null)?->id ?? throw new InvalidArgumentException('Pilih asrama sasaran.');
        } elseif ($audience === AnnouncementAudience::Role) {
            $targetRole = Role::tryFrom((string) ($data['target_role'] ?? '')) ?? throw new InvalidArgumentException('Pilih peran sasaran.');
        }

        $publishedAt = match ($data['publish']) {
            'now' => $announcement?->isPublished() ? $announcement->published_at : now(),
            'schedule' => CarbonImmutable::parse($data['publish_at'] ?? throw new InvalidArgumentException('Isi waktu terbit.')),
            default => null,
        };

        if ($data['publish'] === 'schedule' && $publishedAt->isPast()) {
            throw new InvalidArgumentException('Waktu terbit terjadwal harus di masa depan.');
        }

        $announcement ??= new Announcement(['created_by' => $by->id]);
        $announcement->fill([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'source' => $source,
            'audience' => $audience,
            'target_id' => $targetId,
            'target_role' => $targetRole,
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'notify_whatsapp' => (bool) ($data['notify_whatsapp'] ?? false),
            'published_at' => $publishedAt,
        ])->save();

        // Pembuat tidak perlu melihat penanda "belum dibaca" pada pengumumannya sendiri.
        $this->markRead($announcement, $by);

        if ($announcement->isPublished() && ! $announcement->notified_at) {
            $this->notify($announcement);
        }

        return $announcement;
    }

    /** Kirim notifikasi ke sasaran sekali saja (dipakai juga scheduler untuk pengumuman terjadwal). */
    public function notify(Announcement $announcement): int
    {
        // Tandai lebih dulu agar proses paralel tidak mengirim dobel.
        $claimed = Announcement::query()->whereKey($announcement->id)->whereNull('notified_at')->update(['notified_at' => now()]);
        if (! $claimed) {
            return 0;
        }

        $recipients = $announcement->recipients();
        Notification::send($recipients, new AnnouncementNotification($announcement));

        return $recipients->count();
    }

    public function publishDue(): int
    {
        $sent = 0;
        Announcement::query()->published()->whereNull('notified_at')->get()
            ->each(function (Announcement $announcement) use (&$sent) {
                $sent += $this->notify($announcement);
            });

        return $sent;
    }

    public function markRead(Announcement $announcement, User $user): void
    {
        AnnouncementRead::query()->firstOrCreate(['announcement_id' => $announcement->id, 'user_id' => $user->id], ['read_at' => now()]);
    }
}
