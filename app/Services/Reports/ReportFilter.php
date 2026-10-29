<?php

namespace App\Services\Reports;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Cakupan data laporan & dashboard: rentang tanggal, angkatan, kelas, dan (opsional) peserta tertentu.
 */
final class ReportFilter
{
    /** @var Collection<int, User>|null */
    private ?Collection $participants = null;

    /** @var list<int>|null */
    private ?array $classroomIds = null;

    /**
     * @param  list<int>|null  $userIds
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $batchId = null,
        public readonly ?int $classroomId = null,
        public readonly ?array $userIds = null,
    ) {}

    public static function make(CarbonInterface|string $from, CarbonInterface|string $to, ?int $batchId = null, ?int $classroomId = null): self
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        return $to->lt($from) ? new self($to, $from, $batchId, $classroomId) : new self($from, $to, $batchId, $classroomId);
    }

    public function between(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay(), $this->batchId, $this->classroomId, $this->userIds);
    }

    public function forUser(int $userId): self
    {
        return new self($this->from, $this->to, $this->batchId, $this->classroomId, [$userId]);
    }

    /** @return list<int> */
    public function classroomIds(): array
    {
        return $this->classroomIds ??= Classroom::query()
            ->when($this->batchId, fn ($q) => $q->where('batch_id', $this->batchId))
            ->when($this->classroomId, fn ($q) => $q->whereKey($this->classroomId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Peserta aktif di kelas dalam cakupan, dengan kelas (dan angkatannya) yang relevan.
     *
     * @return Collection<int, User>
     */
    public function participants(): Collection
    {
        $classroomIds = $this->classroomIds();

        return $this->participants ??= User::query()->role(Role::Participant->value)->active()
            ->whereHas('classrooms', fn ($q) => $q->whereIn('classrooms.id', $classroomIds))
            ->when($this->userIds !== null, fn ($q) => $q->whereIn('users.id', $this->userIds))
            ->with(['profile', 'classrooms' => fn ($q) => $q->whereIn('classrooms.id', $classroomIds)->with('batch')])
            ->orderBy('name')
            ->get();
    }

    /** @return list<int> */
    public function participantIds(): array
    {
        return $this->participants()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Hari dalam rentang yang sudah lewat atau hari ini (masa depan tidak dihitung).
     *
     * @return list<CarbonImmutable>
     */
    public function pastDays(): array
    {
        $end = $this->to->min(today()->toImmutable());
        $days = [];

        for ($day = $this->from; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * Minggu (Senin) yang menyentuh rentang.
     *
     * @return list<CarbonImmutable>
     */
    public function weeks(): array
    {
        $weeks = [];

        for ($week = $this->from->startOfWeek(); $week->lte($this->to); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** Nama kelas peserta dalam cakupan, mis. "Kelas Inggris A". */
    public static function classLabel(User $participant): string
    {
        return $participant->classrooms->pluck('name')->join(', ') ?: '–';
    }

    public function cacheKey(string $prefix): string
    {
        return $prefix.':'.md5(json_encode([$this->from->toDateString(), $this->to->toDateString(), $this->batchId, $this->classroomId, $this->userIds]));
    }
}
