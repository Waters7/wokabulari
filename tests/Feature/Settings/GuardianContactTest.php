<?php

use App\Actions\Participants\ImportParticipants;
use App\Enums\GuardianRelationship;
use App\Models\ParticipantProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function participantWithoutGuardian(): User
{
    $user = participant();
    $user->profile->update(['guardian_name' => null, 'guardian_phone' => null, 'guardian_relationship' => null]);

    return $user->fresh();
}

test('peserta tanpa kontak orang tua/wali diarahkan untuk mengisinya', function () {
    $user = participantWithoutGuardian();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('guardian-contact.edit'));
    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('guardian-contact.edit'));
    $this->actingAs($user)->get(route('guardian-contact.edit'))->assertOk()->assertSee('Kontak orang tua/wali');
});

test('peserta tanpa profil juga wajib mengisi kontak orang tua/wali', function () {
    $user = participant();
    $user->profile->delete();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('guardian-contact.edit'));
});

test('peserta yang sudah mengisi dan peran lain tidak diarahkan', function () {
    $this->actingAs(participant())->get(route('dashboard'))->assertOk();
    $this->actingAs(teacher())->get(route('dashboard'))->assertOk();
    $this->actingAs(teacher())->get(route('guardian-contact.edit'))->assertRedirect(route('dashboard'));
});

test('peserta menyimpan kontak orang tua/wali lalu bisa memakai aplikasi', function () {
    $user = participantWithoutGuardian();

    Livewire::actingAs($user)->test('pages::participant.guardian-contact')
        ->set('guardian_name', 'Maria Sondakh')
        ->set('guardian_relationship', 'mother')
        ->set('guardian_phone', '0812-7777-8888')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->profile->fresh())
        ->guardian_name->toBe('Maria Sondakh')
        ->guardian_relationship->toBe(GuardianRelationship::Mother)
        ->guardian_phone->toBe('6281277778888');

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('nomor orang tua/wali harus valid dan berbeda dari nomor peserta', function () {
    $user = participantWithoutGuardian();
    $user->update(['phone' => '6281211110000']);

    Livewire::actingAs($user)->test('pages::participant.guardian-contact')
        ->set('guardian_name', 'Ay')
        ->set('guardian_phone', '12345')
        ->call('save')
        ->assertHasErrors(['guardian_name', 'guardian_phone']);

    Livewire::actingAs($user)->test('pages::participant.guardian-contact')
        ->set('guardian_name', 'Johny Lumi')
        ->set('guardian_relationship', 'father')
        ->set('guardian_phone', '081211110000')
        ->call('save')
        ->assertHasErrors('guardian_phone');

    expect($user->profile->fresh()->hasGuardianContact())->toBeFalse();
});

test('peserta bisa mengubah kontak orang tua/wali dan foto dari halaman profil', function () {
    Storage::fake('public');
    $user = participant();

    Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('guardian_name', 'Paman Rudy')
        ->set('guardian_relationship', 'guardian')
        ->set('guardian_phone', '+62 813 4444 5555')
        ->set('photo', UploadedFile::fake()->image('saya.jpg', 1200, 1200))
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->profile)
        ->guardian_name->toBe('Paman Rudy')
        ->guardian_relationship->toBe(GuardianRelationship::Guardian)
        ->guardian_phone->toBe('6281344445555')
        ->and($user->photo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($user->photo_path);
    expect(getimagesize(Storage::disk('public')->path($user->photo_path))[0])->toBe(600);
});

test('peserta tidak bisa mengosongkan kontak orang tua/wali dari halaman profil', function () {
    $user = participant();

    Livewire::actingAs($user)->test('pages::settings.profile')
        ->set('guardian_phone', '')
        ->call('save')
        ->assertHasErrors('guardian_phone');
});

test('admin mengisi kontak orang tua/wali peserta dari form master', function () {
    $participant = participant();

    Livewire::actingAs(programAdmin())->test('pages::master.participants')
        ->call('edit', $participant->id)
        ->set('form.guardian_name', 'Ibu Lumentut')
        ->set('form.guardian_relationship', 'mother')
        ->set('form.guardian_phone', '085299990000')
        ->call('save')
        ->assertHasNoErrors();

    expect($participant->profile->fresh())
        ->guardian_name->toBe('Ibu Lumentut')
        ->guardian_phone->toBe('6285299990000');

    $this->actingAs(programAdmin())->get(route('master.participants.show', $participant))
        ->assertSee('Ibu Lumentut');
});

test('impor peserta membaca kolom HP orang tua', function () {
    $user = app(ImportParticipants::class)->handle([
        ['nama' => 'Peserta Impor', 'no_hp' => '081200001111', 'nama_orang_tua' => 'Bapak Impor', 'hp_orang_tua' => '081300002222'],
    ], defaultPassword: 'sandi-awal');

    $profile = ParticipantProfile::query()->whereHas('user', fn ($q) => $q->where('phone', '6281200001111'))->firstOrFail();

    expect($user->created)->toBe(1)
        ->and($profile->guardian_name)->toBe('Bapak Impor')
        ->and($profile->guardian_phone)->toBe('6281300002222');
});
