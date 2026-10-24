<?php

use App\Actions\Participants\ImportParticipants;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('import peserta membuat akun dengan No. HP dan wajib ganti kata sandi', function () {
    $classroom = Classroom::factory()->create();

    $result = app(ImportParticipants::class)->handle([
        ['nama' => 'Syaloom Contoh', 'no_hp' => '085800000001', 'asal_sekolah' => 'Poltekkes Kemenkes Manado'],
        ['nama' => 'Tanpa Nomor', 'no_hp' => null, 'asal_sekolah' => 'SMK Uji', 'keterangan' => 'No. HP belum ada'],
        ['nama' => '', 'no_hp' => '0858'],
    ], $classroom, 'smk-uji');

    expect($result->created)->toBe(2)
        ->and($result->skipped)->toBe(1)
        ->and($result->warnings)->toHaveCount(2)
        ->and($result->notes)->toHaveCount(1);

    $user = User::where('phone', '6285800000001')->firstOrFail();
    expect($user->isParticipant())->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check('smk-uji', $user->password))->toBeTrue()
        ->and($user->profile->school_name)->toBe('Poltekkes Kemenkes Manado')
        ->and($user->classrooms->pluck('id')->all())->toBe([$classroom->id]);
});

test('import ulang memperbarui peserta yang sama tanpa duplikat dan tanpa mengubah kata sandi', function () {
    $importer = app(ImportParticipants::class);
    $importer->handle([['nama' => 'Nama Lama', 'no_hp' => '085800000002']], null, 'awal');

    $user = User::where('phone', '6285800000002')->firstOrFail();
    $user->update(['password' => 'sudah-diganti', 'must_change_password' => false]);

    $result = $importer->handle([['nama' => 'Nama Baru', 'no_hp' => '0858-0000-0002', 'asal_sekolah' => 'Unsrat']], null, 'awal');

    expect($result->updated)->toBe(1)->and($result->created)->toBe(0);
    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('sudah-diganti', $user->password))->toBeTrue();
});

test('import menolak berjalan tanpa kata sandi awal', function () {
    config(['wokabulari.participant_default_password' => null]);

    app(ImportParticipants::class)->handle([['nama' => 'X', 'no_hp' => '0811']]);
})->throws(InvalidArgumentException::class);

test('perintah import membaca CSV', function () {
    $file = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($file, "nama,no_hp,asal_sekolah\n\"Laura Uji\",085256000000,\"SMK Trinita Manado\"\n");

    $this->artisan('app:import-participants', ['file' => $file, '--password' => 'smk-uji'])
        ->expectsOutputToContain('1 peserta baru')
        ->assertSuccessful();

    expect(User::where('phone', '6285256000000')->exists())->toBeTrue();
});
