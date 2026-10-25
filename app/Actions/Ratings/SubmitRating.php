<?php

namespace App\Actions\Ratings;

use App\Events\RatingSubmitted;
use App\Models\MealSchedule;
use App\Models\TeacherRating;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Rating mingguan peserta untuk vendor makanan dan pengajar kelasnya (sekali per minggu).
 */
class SubmitRating
{
    public static function weekStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfWeek();
    }

    /**
     * @param  array<string, mixed>  $data  rating, taste, portion, cleanliness, punctuality, comment, suggestion
     */
    public function vendor(User $participant, Vendor $vendor, array $data): VendorRating
    {
        $week = self::weekStart();

        $served = MealSchedule::query()->where('vendor_id', $vendor->id)
            ->whereDateBetween('date', $week->toDateString(), $week->endOfWeek()->toDateString())->exists();

        if (! $served) {
            throw new InvalidArgumentException('Vendor ini tidak melayani konsumsi minggu ini.');
        }

        if (VendorRating::query()->where('vendor_id', $vendor->id)->where('user_id', $participant->id)->whereDate('week_start', $week)->exists()) {
            throw new InvalidArgumentException('Ngana so kase rating vendor ini minggu ini.');
        }

        $rating = VendorRating::query()->create([
            'vendor_id' => $vendor->id,
            'user_id' => $participant->id,
            'week_start' => $week,
        ] + $this->scores($data, array_keys(VendorRating::ASPECTS)));

        event(new RatingSubmitted($rating));

        return $rating;
    }

    /**
     * @param  array<string, mixed>  $data  rating, mastery, delivery, discipline, interaction, comment, suggestion, is_anonymous
     */
    public function teacher(User $participant, User $teacher, array $data): TeacherRating
    {
        $classroom = $participant->classrooms()->where('teacher_id', $teacher->id)->first();

        if (! $classroom) {
            throw new InvalidArgumentException('Pengajar ini tidak mengajar kelas ngana.');
        }

        $week = self::weekStart();

        if (TeacherRating::query()->where('teacher_id', $teacher->id)->where('user_id', $participant->id)->whereDate('week_start', $week)->exists()) {
            throw new InvalidArgumentException('Ngana so kase rating pengajar ini minggu ini.');
        }

        $rating = TeacherRating::query()->create([
            'teacher_id' => $teacher->id,
            'user_id' => $participant->id,
            'classroom_id' => $classroom->id,
            'week_start' => $week,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
        ] + $this->scores($data, array_keys(TeacherRating::ASPECTS)));

        event(new RatingSubmitted($rating));

        return $rating;
    }

    /**
     * @param  list<string>  $aspects
     * @return array<string, mixed>
     */
    protected function scores(array $data, array $aspects): array
    {
        $value = fn ($v) => ($v === null || $v === '') ? null : (int) $v;
        $overall = $value($data['rating'] ?? null);

        if ($overall === null || $overall < 1 || $overall > 5) {
            throw new InvalidArgumentException('Pilih rating 1–5.');
        }

        $scores = ['rating' => $overall];
        foreach ($aspects as $aspect) {
            $score = $value($data[$aspect] ?? null);
            if ($score !== null && ($score < 1 || $score > 5)) {
                throw new InvalidArgumentException('Nilai aspek harus 1–5.');
            }
            $scores[$aspect] = $score;
        }

        return $scores + [
            'comment' => filled($data['comment'] ?? null) ? mb_substr(trim($data['comment']), 0, 2000) : null,
            'suggestion' => filled($data['suggestion'] ?? null) ? mb_substr(trim($data['suggestion']), 0, 2000) : null,
        ];
    }
}
