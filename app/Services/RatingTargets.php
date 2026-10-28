<?php

namespace App\Services;

use App\Actions\Ratings\SubmitRating;
use App\Models\Classroom;
use App\Models\MealSchedule;
use App\Models\TeacherRating;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRating;
use Illuminate\Support\Collection;

/**
 * Vendor dan pengajar yang perlu diberi rating peserta pada minggu berjalan.
 */
class RatingTargets
{
    /**
     * Vendor yang melayani konsumsi minggu ini, dengan rating peserta (bila sudah).
     *
     * @return Collection<int, array{vendor: Vendor, rating: ?VendorRating}>
     */
    public function vendors(User $participant): Collection
    {
        $week = SubmitRating::weekStart();

        $vendorIds = MealSchedule::query()
            ->whereDateBetween('date', $week->toDateString(), $week->endOfWeek()->toDateString())
            ->distinct()->pluck('vendor_id');

        $ratings = VendorRating::query()->where('user_id', $participant->id)->whereDate('week_start', $week)->get()->keyBy('vendor_id');

        return Vendor::query()->whereIn('id', $vendorIds)->orderBy('name')->get()
            ->map(fn (Vendor $vendor) => ['vendor' => $vendor, 'rating' => $ratings->get($vendor->id)]);
    }

    /**
     * Pengajar kelas aktif peserta, dengan rating peserta minggu ini (bila sudah).
     *
     * @return Collection<int, array{teacher: User, classroom: Classroom, rating: ?TeacherRating}>
     */
    public function teachers(User $participant): Collection
    {
        $week = SubmitRating::weekStart();
        $ratings = TeacherRating::query()->where('user_id', $participant->id)->whereDate('week_start', $week)->get()->keyBy('teacher_id');

        // Setiap pengajar di tim pengajar kelas dinilai terpisah.
        return $participant->classrooms()->active()->with(['teachers', 'language'])->get()
            ->flatMap(fn (Classroom $classroom) => $classroom->teachers->map(fn (User $teacher) => [
                'teacher' => $teacher, 'classroom' => $classroom, 'rating' => $ratings->get($teacher->id),
            ]))
            ->unique(fn (array $row) => $row['teacher']->id)
            ->values();
    }

    /** Jumlah rating yang belum diisi minggu ini. */
    public function pendingCount(User $participant): int
    {
        return $this->vendors($participant)->whereNull('rating')->count()
            + $this->teachers($participant)->whereNull('rating')->count();
    }
}
