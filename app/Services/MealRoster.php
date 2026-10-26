<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Daftar peserta penerima konsumsi dan penanda penyesuaian porsi (izin, sakit, keluar asrama).
 */
class MealRoster
{
    /** Sumber penanda tambahan dari modul lain (asrama, kesehatan). */
    protected static array $flagSources = [];

    /**
     * @param  callable(CarbonInterface, Collection<int, int>): array<int, list<string>>  $source
     */
    public static function addFlagSource(string $key, callable $source): void
    {
        // Berkunci agar tidak terdaftar ganda saat aplikasi di-boot ulang (mis. di test).
        static::$flagSources[$key] = $source;
    }

    public function __construct(protected SettingService $settings) {}

    /**
     * Penerima konsumsi; bila jenisnya diberikan, peserta tidak asrama hanya ikut
     * untuk jenis yang diatur tim konsumsi.
     *
     * @return Collection<int, User>
     */
    public function participants(?MealType $type = null): Collection
    {
        $batch = Batch::current();

        return User::query()
            ->role(Role::Participant->value)
            ->active()
            ->when($batch, fn ($q) => $q->whereHas('classrooms', fn ($c) => $c->where('batch_id', $batch->id)))
            ->when($type && ! $this->servesNonResidents($type), fn ($q) => $q->whereDoesntHave('profile', fn ($p) => $p->where('lives_in_dorm', false)))
            ->with(['profile', 'currentRoomAssignment.room.dormitory'])
            ->orderBy('name')
            ->get();
    }

    /** Jenis konsumsi untuk peserta tidak asrama. @return list<MealType> */
    public function nonResidentTypes(): array
    {
        $values = $this->settings->get('meals.non_resident_types', config('wokabulari.meals.non_resident_types', []));

        return array_values(array_filter(array_map(fn ($value) => MealType::tryFrom((string) $value), (array) $values)));
    }

    public function servesNonResidents(MealType $type): bool
    {
        return in_array($type, $this->nonResidentTypes(), true);
    }

    /** Apakah peserta berhak atas jenis konsumsi ini. */
    public function isEligible(User $participant, MealType $type): bool
    {
        return $participant->livesInDorm() || $this->servesNonResidents($type);
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return array<int, list<string>> user_id => label penanda
     */
    public function flags(CarbonInterface $date, Collection $userIds): array
    {
        $flags = [];

        LeaveRequest::query()->approved()->covering($date)->whereIn('user_id', $userIds)->get()
            ->each(function (LeaveRequest $leave) use (&$flags) {
                $flags[$leave->user_id][] = $leave->type->label().' kelas';
            });

        foreach (static::$flagSources as $source) {
            foreach ($source($date, $userIds) as $userId => $labels) {
                $flags[$userId] = array_merge($flags[$userId] ?? [], $labels);
            }
        }

        return $flags;
    }
}
