<?php

use App\Enums\Gender;
use App\Models\ParticipantProfile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('peserta melengkapi biodata dari menu profil; kelengkapan naik dan pengingat beranda hilang', function () {
    $participant = participant();
    $participant->profile->update(['gender' => null, 'birth_date' => null, 'school_name' => null, 'major' => null, 'emergency_contact_name' => null, 'emergency_contact_phone' => null]);

    $this->actingAs($participant)->get(route('dashboard'))->assertSee('data-test="biodata-reminder"', false)->assertSee('Lengkapi biodata');
    $this->actingAs($participant)->get(route('profile.edit'))->assertSee(route('biodata.edit'));

    Livewire::actingAs($participant)->test('pages::settings.biodata')
        ->set('form.birth_place', 'Tomohon')
        ->set('form.birth_date', '2009-03-12')
        ->set('form.gender', 'female')
        ->set('form.address', 'Jl. Raya Tomohon No. 5')
        ->set('form.school_name', 'SMK Kristen 1 Tomohon')
        ->set('form.school_grade', 'XII')
        ->set('form.major', 'Keperawatan')
        ->set('form.blood_type', 'O')
        ->set('form.emergency_contact_name', 'Om Robert')
        ->set('form.emergency_contact_phone', '0812-3456-7890')
        ->set('form.shirt_size', 'M')
        ->call('save')->assertHasNoErrors()
        ->assertSet('genderLocked', true);

    $profile = $participant->profile->fresh();
    expect($profile)
        ->birth_place->toBe('Tomohon')
        ->gender->toBe(Gender::Female)
        ->emergency_contact_phone->toBe('6281234567890')
        ->blood_type->toBe('O')
        ->biodata_updated_at->not->toBeNull()
        ->and($profile->biodataCompletion())->toBe(100)
        // Golongan darah tersimpan terenkripsi.
        ->and(DB::table('participant_profiles')->where('id', $profile->id)->value('blood_type'))->not->toBe('O');

    $this->actingAs($participant)->get(route('dashboard'))->assertDontSee('data-test="biodata-reminder"', false);
    $this->actingAs(programAdmin())->get(route('master.participants.show', $participant))
        ->assertSee('Tomohon, 12 Mar 2009')->assertSee('XII · Keperawatan')->assertSee('100% lengkap');
});

test('jenis kelamin dikunci setelah terisi, nomor HP divalidasi, dan hanya peserta yang bisa membuka', function () {
    $participant = participant();
    $participant->profile->update(['gender' => Gender::Male]);

    Livewire::actingAs($participant)->test('pages::settings.biodata')
        ->assertSet('genderLocked', true)
        ->set('form.gender', 'female')
        ->set('form.emergency_contact_phone', 'abc')
        ->call('save')->assertHasErrors('form.emergency_contact_phone')
        ->set('form.emergency_contact_phone', '')
        ->set('form.school_grade', 'XV')
        ->call('save')->assertHasErrors('form.school_grade')
        ->set('form.school_grade', 'XI')
        ->call('save')->assertHasNoErrors();

    expect($participant->profile->fresh()->gender)->toBe(Gender::Male)
        ->and($participant->profile->fresh()->school_grade)->toBe('XI');

    $this->actingAs(teacher())->get(route('biodata.edit'))->assertForbidden();
    $this->actingAs(guardian())->get(route('biodata.edit'))->assertForbidden();
});

test('kelengkapan biodata dihitung dari isian inti', function () {
    $profile = ParticipantProfile::factory()->make(array_fill_keys(ParticipantProfile::BIODATA_FIELDS, null));
    expect($profile->biodataCompletion())->toBe(0);

    $profile->fill(['birth_place' => 'Manado', 'school_name' => 'SMK N 1 Manado']);
    expect($profile->biodataCompletion())->toBe(20);
});
