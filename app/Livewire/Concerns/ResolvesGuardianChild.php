<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Halaman portal wali: memilih anak (?anak=ID) hanya dari peserta yang diwalikan & terverifikasi.
 * Mencoba membuka peserta lain → 403.
 */
trait ResolvesGuardianChild
{
    #[Url(as: 'anak')]
    public ?int $childId = null;

    /** @var Collection<int, User>|null */
    protected ?Collection $wardCache = null;

    public function mountResolvesGuardianChild(): void
    {
        abort_unless(auth()->user()->isGuardian(), 403);
        $this->child();
    }

    /** @return Collection<int, User> */
    protected function children(): Collection
    {
        return $this->wardCache ??= auth()->user()->wards()
            ->with(['profile', 'currentRoomAssignment.room.dormitory'])
            ->orderBy('name')->get();
    }

    protected function child(): ?User
    {
        $children = $this->children();

        if ($this->childId !== null) {
            $child = $children->firstWhere('id', $this->childId);
            abort_unless($child, 403, 'Bapak/Ibu hanya dapat melihat data anak sendiri.');

            return $child;
        }

        return $children->first();
    }

    public function selectChild(int $id): void
    {
        abort_unless($this->children()->contains('id', $id), 403);
        $this->childId = $id;
    }
}
