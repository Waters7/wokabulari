<?php

namespace App\Actions\Assessments;

use App\Models\Classroom;
use App\Services\PeriodicAspects;

/**
 * Pengajar memilih aspek penilaian periodik untuk kelasnya. Daftar kosong = kembali ke aspek bawaan.
 * Nilai yang sudah tersimpan tidak terhapus walau aspeknya tidak dipilih lagi.
 */
class SetClassroomAspects
{
    public function __construct(protected PeriodicAspects $aspects) {}

    /**
     * @param  list<int|string>  $ids
     */
    public function handle(Classroom $classroom, array $ids): void
    {
        $allowed = $this->aspects->available($classroom)->pluck('id');

        $classroom->assessmentAspects()->sync(
            collect($ids)->map(fn ($id) => (int) $id)->unique()->intersect($allowed)->values()->all()
        );
    }
}
