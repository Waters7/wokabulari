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

    /**
     * Tim pengajar/tim kerja yang ditandai menerima konsumsi (bukan peserta); bila jenisnya diberikan,
     * hanya untuk jenis yang diatur tim konsumsi.
     *
     * @return Collection<int, User>
     */
    public function staff(?MealType $type = null): Collection
    {
        if ($type && ! $this->servesStaff($type)) {
            return collect();
        }

        return User::query()->where('receives_meals', true)->active()
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', Role::Participant->value))
            ->with('roles')->orderBy('name')->get();
    }

    /**
     * Semua penerima konsumsi: peserta + tim yang ditandai menerima konsumsi.
     *
     * @return Collection<int, User>
     */
    public function recipients(?MealType $type = null): Collection
    {
        return $this->participants($type)->concat($this->staff($type))->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** Jenis konsumsi untuk tim pengajar/tim kerja penerima konsumsi. @return list<MealType> */
    public function staffTypes(): array
    {
        $values = $this->settings->get('meals.staff_types', config('wokabulari.meals.staff_types', []));

        return array_values(array_filter(array_map(fn ($value) => MealType::tryFrom((string) $value), (array) $values)));
    }

    public function servesStaff(MealType $type): bool
    {
        return in_array($type, $this->staffTypes(), true);
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

    /** Apakah pengguna berhak atas jenis konsumsi ini (peserta: aturan tidak asrama; tim: jenis konsumsi tim). */
    public function isEligible(User $user, MealType $type): bool
    {
        if (! $user->isParticipant()) {
            return $user->receives_meals && $user->is_active && $this->servesStaff($type);
        }

        return $user->livesInDorm() || $this->servesNonResidents($type);
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
