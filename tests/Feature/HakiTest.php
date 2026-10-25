<?php

use App\Support\Haki;

test('pernyataan HaKI yang tertanam bertanda tangan sah', function () {
    expect(Haki::PUBLIC_KEY)->not->toBe('')
        ->and(Haki::verify())->toBeTrue()
        ->and(Haki::STATEMENT['pemegang_hak_cipta'])->toBe('Poltekkes Kemenkes Manado')
        ->and(Haki::fingerprint())->toMatch('/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/');
});

test('pernyataan yang diubah tidak lolos verifikasi', function () {
    $tampered = ['pemegang_hak_cipta' => 'Pihak Lain'] + Haki::STATEMENT;

    expect(Haki::verifyWith(Haki::PUBLIC_KEY, Haki::SIGNATURE, Haki::message($tampered)))->toBeFalse()
        ->and(Haki::verifyWith(Haki::PUBLIC_KEY, Haki::SIGNATURE, Haki::message(Haki::STATEMENT, Haki::SEALED.'x')))->toBeFalse();
});

test('segel hanya terbuka dengan kunci yang benar dan kepemilikan terbukti dengan kunci privat', function () {
    $issued = Haki::issue(['kontak_pencipta' => 'pengembang@contoh.test']);

    expect(Haki::verifyWith($issued['public_key'], $issued['signature'], Haki::message(Haki::STATEMENT, $issued['sealed'])))->toBeTrue()
        ->and(Haki::open($issued['seal_key'], $issued['sealed']))->toMatchArray(['pencipta' => 'Waters7', 'kontak_pencipta' => 'pengembang@contoh.test'])
        ->and(Haki::open(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), $issued['sealed']))->toBeNull()
        ->and(Haki::ownsKey($issued['sign_secret'], $issued['public_key']))->toBeTrue()
        ->and(Haki::ownsKey($issued['sign_secret']))->toBeFalse();
});

test('perintah app:haki memeriksa dan membuktikan kepemilikan dari berkas kunci', function () {
    $this->artisan('app:haki')->expectsOutputToContain('Tanda tangan HaKI sah')->assertSuccessful();

    $file = tempnam(sys_get_temp_dir(), 'haki');
    $issued = Haki::issue([]);
    file_put_contents($file, "HAKI_SIGN_SECRET={$issued['sign_secret']}\nHAKI_SEAL_KEY={$issued['seal_key']}\n");

    // Kunci orang lain tidak bisa mengaku sebagai pemilik.
    $this->artisan('app:haki', ['--berkas' => $file])->expectsOutputToContain('BUKAN pasangan')->assertFailed();

    unlink($file);
});

test('penandatanganan HaKI ditolak di produksi', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('app:haki-sign')->assertFailed();
});

test('sidik HaKI dan versi tampil di halaman login serta header respons', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Wokabulari-HaKI', Haki::fingerprint())
        ->assertSee('Wokabulari versi '.config('wokabulari.version'))
        ->assertSee('Sidik HaKI '.Haki::fingerprint())
        ->assertSee('name="copyright"', false);
});

test('tombol keluar dan ganti kata sandi tersedia di bagian atas', function () {
    $this->actingAs(participant())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="logout-top"', false)
        ->assertSee('Ganti kata sandi');
});
