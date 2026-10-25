<?php

use App\Actions\Ratings\SubmitRating;
use App\Events\RatingSubmitted;
use App\Models\Classroom;
use App\Models\MealSchedule;
use App\Models\TeacherRating;
use App\Notifications\RatingReminderNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function ratingClass(): array
{
    $classroom = Classroom::factory()->withParticipants(2)->create();
    $classroom->batch->update(['is_active' => true]);

    return [$classroom, $classroom->participants()->orderBy('users.id')->first()];
}

test('rating vendor tidak bisa dua kali dalam minggu yang sama', function () {
    Event::fake([RatingSubmitted::class]);
    $schedule = MealSchedule::factory()->create();
    $participant = participant();

    app(SubmitRating::class)->vendor($participant, $schedule->vendor, ['rating' => 4, 'taste' => 5]);
    Event::assertDispatched(RatingSubmitted::class);

    expect(fn () => app(SubmitRating::class)->vendor($participant, $schedule->vendor, ['rating' => 5]))
        ->toThrow(InvalidArgumentException::class, 'minggu ini');
});

test('rating vendor minggu berikutnya diperbolehkan', function () {
    $schedule = MealSchedule::factory()->create();
    $participant = participant();
    app(SubmitRating::class)->vendor($participant, $schedule->vendor, ['rating' => 4]);

    $this->travel(1)->weeks();
    MealSchedule::factory()->for($schedule->vendor)->create();

    expect(app(SubmitRating::class)->vendor($participant, $schedule->vendor, ['rating' => 3])->rating)->toBe(3);
});

test('rating pengajar sekali per minggu dan hanya untuk pengajar kelasnya', function () {
    [$classroom, $participant] = ratingClass();

    app(SubmitRating::class)->teacher($participant, $classroom->teacher, ['rating' => 5, 'is_anonymous' => true]);

    expect(fn () => app(SubmitRating::class)->teacher($participant, $classroom->teacher, ['rating' => 4]))
        ->toThrow(InvalidArgumentException::class, 'minggu ini');
    expect(fn () => app(SubmitRating::class)->teacher($participant, teacher(), ['rating' => 4]))
        ->toThrow(InvalidArgumentException::class, 'tidak mengajar');
});

test('rating anonim tersembunyi dari pengajar tetapi terlihat oleh admin', function () {
    [$classroom, $participant] = ratingClass();
    app(SubmitRating::class)->teacher($participant, $classroom->teacher, ['rating' => 5, 'comment' => 'Mantap sekali', 'is_anonymous' => true]);

    $this->actingAs($classroom->teacher)->get(route('teach.ratings'))
        ->assertOk()->assertSee('Mantap sekali')->assertSee('Anonim')->assertDontSee($participant->name);

    $this->actingAs(programAdmin())->get(route('manage.teacher-ratings'))
        ->assertOk()->assertSee('Mantap sekali')->assertSee($participant->name);

    $this->actingAs($classroom->teacher)->get(route('manage.teacher-ratings'))->assertForbidden();
});

test('identitas penilai tidak ikut terserialisasi', function () {
    $rating = TeacherRating::factory()->anonymous()->create();

    expect($rating->toArray())->not->toHaveKey('user_id');
});

test('peserta mengisi rating lewat halaman rating mingguan', function () {
    [$classroom, $participant] = ratingClass();
    $schedule = MealSchedule::factory()->create();

    Livewire::actingAs($participant)->test('pages::ratings.index')
        ->assertSee($schedule->vendor->name)->assertSee($classroom->teacher->name)
        ->call('open', 'vendor', $schedule->vendor_id)
        ->call('submit')->assertHasErrors('form.rating')
        ->set('form.rating', 4)->set('form.taste', 5)->set('form.comment', 'Enak')
        ->call('submit')->assertHasNoErrors()
        ->call('open', 'teacher', $classroom->teacher_id)
        ->set('form.rating', 5)
        ->call('submit')->assertHasNoErrors();

    expect(TeacherRating::query()->where('teacher_id', $classroom->teacher_id)->first()->is_anonymous)->toBeTrue();
});

test('pengingat Jumat hanya untuk peserta yang belum mengisi', function () {
    Notification::fake();
    [$classroom, $first] = ratingClass();
    $second = $classroom->participants()->whereKeyNot($first->id)->first();
    $schedule = MealSchedule::factory()->create();

    app(SubmitRating::class)->vendor($first, $schedule->vendor, ['rating' => 4]);
    app(SubmitRating::class)->teacher($first, $classroom->teacher, ['rating' => 4]);

    $this->artisan('app:rating-reminders')->assertSuccessful();

    Notification::assertNotSentTo($first, RatingReminderNotification::class);
    Notification::assertSentTo($second, RatingReminderNotification::class, fn ($n) => $n->pending === 2);
});
