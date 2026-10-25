<?php

namespace App\Services\Gamification;

use App\Enums\PointEvent;
use App\Events\PointsAwarded;
use App\Models\GamificationRule;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Pencatatan poin gamifikasi. Idempoten: satu event untuk referensi yang sama hanya tercatat sekali
 * (dijaga unique index user_id + event_key + reference_type + reference_id).
 */
class PointService
{
    /** Mode senyap (backfill data lama): tanpa notifikasi naik level & evaluasi lencana per transaksi. */
    public static bool $quiet = false;

    /** @var Collection<string, GamificationRule>|null */
    protected ?Collection $rules = null;

    public function rule(PointEvent $event): ?GamificationRule
    {
        $this->rules ??= GamificationRule::query()->get()->keyBy(fn (GamificationRule $r) => $r->event_key->value);

        return $this->rules->get($event->value);
    }

    public function flushRules(): void
    {
        $this->rules = null;
    }

    /**
     * Berikan poin sesuai aturan aktif.
     *
     * @param  Model|array{0: string, 1: int}|null  $reference  model atau [tipe, id] (mis. ['rating_week', 202643])
     * @param  int|null  $points  poin khusus (mis. quiz: skor% ÷ 5), dibatasi poin aturan bila positif
     */
    public function award(User $user, PointEvent $event, Model|array|null $reference, ?int $points = null, ?string $description = null, ?CarbonInterface $at = null): ?PointTransaction
    {
        if (! $user->isParticipant()) {
            return null;
        }

        $rule = $this->rule($event);
        if (! $rule || ! $rule->is_active) {
            return null;
        }

        $value = $points === null ? $rule->points : ($rule->points > 0 ? min($points, $rule->points) : $points);
        if ($value === 0) {
            return null;
        }

        [$type, $id] = $this->reference($reference);
        $at ??= now();

        if (PointTransaction::query()->where(['user_id' => $user->id, 'event_key' => $event->value, 'reference_type' => $type, 'reference_id' => $id])->exists()) {
            return null;
        }

        if ($rule->max_per_day) {
            $today = PointTransaction::query()->where('user_id', $user->id)->where('event_key', $event->value)
                ->whereBetween('created_at', [$at->copy()->startOfDay(), $at->copy()->endOfDay()])->count();
            if ($today >= $rule->max_per_day) {
                return null;
            }
        }

        try {
            $transaction = PointTransaction::query()->create([
                'user_id' => $user->id,
                'event_key' => $event,
                'points' => $value,
                'reference_type' => $type,
                'reference_id' => $id,
                'description' => $description ?? $rule->name,
                'created_at' => $at,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Event yang sama terproses bersamaan: cukup sekali.
            return null;
        }

        event(new PointsAwarded($transaction));

        return $transaction;
    }

    /**
     * Penyesuaian manual oleh admin (tercatat dengan pembuat dan alasan).
     */
    public function adjust(User $participant, int $points, string $reason, User $by): PointTransaction
    {
        $transaction = PointTransaction::query()->create([
            'user_id' => $participant->id,
            'event_key' => PointEvent::Manual,
            'points' => $points,
            'description' => trim($reason),
            'created_by' => $by->id,
        ]);

        activity('gamifikasi')->causedBy($by)->performedOn($participant)
            ->withProperties(['poin' => $points, 'alasan' => $reason])->log('Penyesuaian poin manual');

        event(new PointsAwarded($transaction));

        return $transaction;
    }

    public function total(User $user): int
    {
        return (int) PointTransaction::query()->where('user_id', $user->id)->sum('points');
    }

    public function between(User $user, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) PointTransaction::query()->where('user_id', $user->id)->whereBetween('created_at', [$from, $to])->sum('points');
    }

    public function count(User $user, PointEvent $event): int
    {
        return PointTransaction::query()->where('user_id', $user->id)->where('event_key', $event->value)->count();
    }

    /** @return array{0: ?string, 1: ?int} */
    protected function reference(Model|array|null $reference): array
    {
        if ($reference instanceof Model) {
            return [$reference->getMorphClass(), (int) $reference->getKey()];
        }

        return $reference ? [(string) $reference[0], (int) $reference[1]] : [null, null];
    }
}
