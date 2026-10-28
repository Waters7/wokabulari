<?php

use App\Actions\Ratings\SubmitRating;
use App\Models\Classroom;
use App\Models\LearningSession;
use App\Services\RatingTargets;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('admin mengatur pengajar utama dan pengajar lain dalam satu kelas', function () {
    $classroom = Classroom::factory()->create();
    $lead = teacher();
    $co = teacher();

    Livewire::actingAs(programAdmin())->test('pages::master.classrooms')
        ->call('edit', $classroom->id)
        ->set('form.teacher_id', (string) $lead->id)
        ->set('form.co_teacher_ids', [(string) $co->id])
        ->call('save')
        ->assertHasNoErrors();

    $classroom->refresh();
    expect($classroom->teacher_id)->toBe($lead->id)
        ->and($classroom->teachers()->pluck('users.id')->sort()->values()->all())->toBe(collect([$lead->id, $co->id])->sort()->values()->all());

    // Form ubah menampilkan pengajar lain tanpa pengajar utama.
    Livewire::actingAs(programAdmin())->test('pages::master.classrooms')
        ->call('edit', $classroom->id)
        ->assertSet('form.co_teacher_ids', [(string) $co->id]);

    $this->actingAs(programAdmin())->get(route('master.classrooms'))->assertSee($co->name);
});

test('pengajar lain punya akses mengajar yang sama dengan pengajar utama', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $co = teacher();
    $classroom->teachers()->attach($co->id);
    $session = LearningSession::factory()->for($classroom)->create(['teacher_id' => $classroom->teacher_id]);
    $outsider = teacher();

    expect($co->can('teach', $classroom))->toBeTrue()
        ->and($co->can('update', $session))->toBeTrue()
        ->and($co->can('view', $classroom->participants->first()))->toBeTrue()
        ->and(Classroom::query()->managedBy($co)->pluck('id')->all())->toBe([$classroom->id])
        ->and($outsider->can('teach', $classroom))->toBeFalse()
        ->and($outsider->can('update', $session))->toBeFalse();
});

test('peserta memberi rating terpisah untuk tiap pengajar kelas', function () {
    $classroom = Classroom::factory()->withParticipants(1)->create();
    $co = teacher();
    $classroom->teachers()->attach($co->id);
    $participant = $classroom->participants->first();

    $targets = app(RatingTargets::class)->teachers($participant);
    expect($targets)->toHaveCount(2)
        ->and($targets->pluck('teacher.id')->all())->toContain($classroom->teacher_id, $co->id);

    $rating = app(SubmitRating::class)->teacher($participant, $co, [
        'rating' => 5, 'mastery' => 5, 'delivery' => 4, 'discipline' => 5, 'interaction' => 4,
    ]);

    expect($rating->classroom_id)->toBe($classroom->id)
        ->and(app(RatingTargets::class)->teachers($participant)->whereNull('rating'))->toHaveCount(1);
});

test('migrasi menyalin pengajar utama lama ke tim pengajar kelas', function () {
    $classroom = Classroom::factory()->create();

    // Kelas baru otomatis memasukkan pengajar utama ke tim pengajar.
    expect(DB::table('classroom_teacher')->where('classroom_id', $classroom->id)->pluck('user_id')->all())->toBe([$classroom->teacher_id]);
});
