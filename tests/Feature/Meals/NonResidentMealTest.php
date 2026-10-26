<?php

use App\Actions\Meals\ReceiveMeal;
use App\Enums\MealType;
use App\Enums\StaffUnit;
use App\Models\Classroom;
use App\Models\MealSchedule;
use App\Models\Vendor;
use App\Services\MealRoster;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('private');
});

function snackSignature(): string
{
    return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/icon-96.png')));
}

test('jenis snack pagi dan sore tersedia dengan jam bawaan', function () {
    expect(MealType::MorningSnack->label())->toBe('Snack pagi')
        ->and(MealType::AfternoonSnack->defaultWindow())->toBe(['15:15', '16:00'])
        ->and(MealType::MorningSnack->isSnack())->toBeTrue()
        ->and(MealType::Lunch->isSnack())->toBeFalse();

    Vendor::factory()->create();

    Livewire::actingAs(staff())->test('pages::meals.schedules')
        ->call('create', today()->toDateString(), 'morning_snack')
        ->assertSet('form.start_time', '09:45')
        ->set('form.menu', 'Panada, teh manis')
        ->call('save')
        ->assertHasNoErrors();

    expect(MealSchedule::query()->where('meal_type', 'morning_snack')->exists())->toBeTrue();
});

test('peserta tidak asrama hanya menerima konsumsi yang diatur tim konsumsi', function () {
    $classroom = Classroom::factory()->create();
    $resident = participant();
    $commuter = participant();
    $commuter->profile->update(['lives_in_dorm' => false]);
    $classroom->participants()->attach([$resident->id => ['joined_at' => today()], $commuter->id => ['joined_at' => today()]]);
    $roster = app(MealRoster::class);

    // Bawaan: snack pagi, makan siang, snack sore.
    expect($roster->participants(MealType::Lunch)->pluck('id'))->toContain($commuter->id)
        ->and($roster->participants(MealType::Dinner)->pluck('id'))->not->toContain($commuter->id)->toContain($resident->id)
        ->and($roster->participants(MealType::AfternoonSnack))->toHaveCount(2);

    $dinner = MealSchedule::factory()->create(['meal_type' => MealType::Dinner]);
    expect(fn () => app(ReceiveMeal::class)->handle($dinner, $commuter, snackSignature()))
        ->toThrow(InvalidArgumentException::class, 'tidak termasuk konsumsi peserta tidak asrama');

    $this->actingAs($commuter)->get(route('meals.index'))->assertDontSee($dinner->menu);

    // Tim konsumsi mengubah: peserta tidak asrama juga dapat makan malam, tidak dapat snack.
    Livewire::actingAs(staff([StaffUnit::Consumption]))->test('pages::meals.schedules')
        ->set('nonResidentTypes', ['lunch', 'dinner'])
        ->call('saveNonResident')
        ->assertHasNoErrors();

    $roster = app(MealRoster::class);
    expect($roster->nonResidentTypes())->toBe([MealType::Lunch, MealType::Dinner])
        ->and($roster->participants(MealType::MorningSnack)->pluck('id'))->not->toContain($commuter->id);

    expect(app(ReceiveMeal::class)->handle($dinner, $commuter, snackSignature())->user_id)->toBe($commuter->id);
});

test('pengaturan konsumsi tidak asrama hanya untuk tim konsumsi', function () {
    Livewire::actingAs(teacher())->test('pages::meals.schedules')
        ->set('nonResidentTypes', ['dinner'])
        ->call('saveNonResident')
        ->assertForbidden();
});
