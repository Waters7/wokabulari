<?php

use App\Actions\Dorm\ManageExitPermits;
use App\Enums\ExitPermitStatus;
use App\Enums\ExitPermitType;
use App\Enums\StaffUnit;
use App\Models\DormRoom;
use App\Models\DormRoomAssignment;
use App\Models\User;
use App\Notifications\ExitPermitNotification;
use App\Support\Navigation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2026-10-26 10:00', 'Asia/Makassar'));
});
afterEach(fn () => Carbon::setTestNow());

function exitResident(): User
{
    $user = User::factory()->participant()->create();
    DormRoomAssignment::factory()->create(['dorm_room_id' => DormRoom::factory()->create()->id, 'user_id' => $user->id]);

    return $user->fresh();
}

function exitRequest(User $user)
{
    return app(ManageExitPermits::class)->submit($user, [
        'type' => ExitPermitType::Temporary->value, 'reason' => 'Beli perlengkapan', 'destination' => 'Mega Mall',
        'contact_phone' => '081234567890', 'planned_departure' => now()->addHours(5), 'planned_return' => now()->addHours(7),
    ]);
}

test('admin program diberi tahu dan bisa menyetujui izin keluar asrama dari halaman Izin Keluar', function () {
    $admin = programAdmin();
    $manager = staff([StaffUnit::Dormitory]);
    $permit = exitRequest(exitResident());

    Notification::assertSentTo([$admin, $manager], ExitPermitNotification::class, fn ($n) => $n->event === 'review_needed');
    expect(collect(app(Navigation::class)->sidebar($admin))->flatMap(fn ($g) => $g['items'])->pluck('label'))->toContain('Izin Keluar');

    $this->actingAs($admin)->get(route('dorm.manage.exits'))->assertOk()->assertSee('Mega Mall');
    Livewire::actingAs($admin)->test('pages::dorm.manage-exits')->call('ask', $permit->id, true)->call('decide')->assertHasNoErrors();

    expect($permit->fresh())->status->toBe(ExitPermitStatus::Approved)->approved_by->toBe($admin->id);
});

test('pengelola asrama tetap bisa menyetujui; tim lain tidak', function () {
    $permit = exitRequest(exitResident());
    $manager = staff([StaffUnit::Dormitory]);

    app(ManageExitPermits::class)->decide($permit, $manager, true);
    expect($permit->fresh()->approved_by)->toBe($manager->id);

    $this->actingAs(staff([StaffUnit::Consumption]))->get(route('dorm.manage.exits'))->assertForbidden();
    $this->actingAs(teacher())->get(route('dorm.manage.exits'))->assertForbidden();
});
