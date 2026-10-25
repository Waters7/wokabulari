<?php

namespace App\Support;

/**
 * Pernyataan Hak Kekayaan Intelektual (HaKI) Wokabulari yang ditanam di kode.
 *
 * - STATEMENT : pernyataan hak cipta terbuka (pencipta & pemegang hak).
 * - SEALED    : pernyataan + data pencipta yang dienkripsi XSalsa20-Poly1305 (sodium secretbox);
 *               hanya pemegang kunci segel yang dapat membukanya.
 * - SIGNATURE : tanda tangan Ed25519 atas STATEMENT + SEALED. Kunci privatnya disimpan
 *               pemegang hak di luar repo, jadi tanda tangan sah hanya bisa dibuat pemiliknya.
 *
 * Mengubah STATEMENT tanpa menandatangani ulang membuat verifikasi gagal.
 * Periksa: `php artisan app:haki`; tandatangani ulang: `php artisan app:haki-sign`.
 */
final class Haki
{
    public const STATEMENT = [
        'karya' => 'Wokabulari — Aplikasi Kelas Bahasa Program SMK Go Global',
        'jenis' => 'Program Komputer',
        'pencipta' => 'Waters7',
        'pemegang_hak_cipta' => 'Poltekkes Kemenkes Manado',
        'tahun' => '2026',
        'ketentuan' => 'Dilindungi UU No. 28 Tahun 2014 tentang Hak Cipta. Dilarang menggandakan, mengubah, atau mendistribusikan tanpa izin tertulis pemegang hak cipta.',
    ];

    // Diisi otomatis oleh `app:haki-sign`.
    public const PUBLIC_KEY = 'riUY+2WzSAVa0sa1nmiwk4TGlwXjktV0dTWhiwr+AuM=';

    public const SEALED = 'UR9SDqignjsd/7M7cFEOX4wnpdfyagBuGarRcBvi0PhrNKsWCvBVcoKsHG1VkYStE/0Iaiu4RinWrmknTacLhXD52/efQxFP3zSSUFPn11irF3IPp0OeE0sGTS+UW1fZqVnmgQgffRRm6Go0JRGevPK+vgv8wEw4LCbPzTumOgqmEM3xTZn/EqVsTnDzMYuSVd2psx4XQsxZROx2g6+Wf82NPoLeu3Pws0d96YMUQ1xT90PCshHlbuX8+IPRq1qGW63u+LR0TK72xZk5wLipiprE16kvvrsInRw//rbE20vYlCpy6d4mJJVCnvx7QAp8ksMOD6nI9rhdlo+xGSbEunsQiCCS+cYrBbtezyCyB3ciPDC9RG9Yh4MaqnGCPJNraXyKEEtGPKFpCMQ3yu30PowG2Hpzkgk2Lx2yDQVYlcOgZYdvyyULSQ5XLFl8Jcld+ZkUA0V/xYJStb6aTRMGMdU57D8I590lRWEpocq0tqvat2DXdv+TuqDrcXY3Sxw+8yovTZdoOH1NgzWymdhB/zOK/qd1Pl8R6rw1+YwFFYyM/PLGJEm/N1jGn5bg10unPNmaRgoQZth3//sTvW+MffrHghpPQGRtIBlT8gDuSRTCmBXIK86GpZTs27M3KmqnHD7mmNgwEoCUmyjXWLDCcKIhB52FSj8JfkjRj+bU38zd38VWFmV6vLuKRyKST81z+bmYM4K679oIfwgUfEJqyAreD6oLnOlhL33E';

    public const SIGNATURE = 'eEMAFH2+LQ44Ff2dkzUtYwKM30tUx79McyuMl1Y+4Zmjnu69yoBrEzpIPpYpruW+rxMEaCUXqPFdGs50XSg2Aw==';

    /** Pesan yang ditandatangani: pernyataan kanonis + segel. */
    public static function message(array $statement = self::STATEMENT, string $sealed = self::SEALED): string
    {
        return 'WOKABULARI-HAKI-v1'."\n".json_encode($statement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n".$sealed;
    }

    public static function verify(): bool
    {
        return self::verifyWith(self::PUBLIC_KEY, self::SIGNATURE, self::message());
    }

    public static function verifyWith(string $publicKey, string $signature, string $message): bool
    {
        $publicKey = base64_decode($publicKey, true);
        $signature = base64_decode($signature, true);

        if (! $publicKey || ! $signature || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    /** Sidik pendek untuk ditampilkan di halaman & header, mis. 3F9A-0C12-B7E4. */
    public static function fingerprint(string $publicKey = self::PUBLIC_KEY, string $signature = self::SIGNATURE): string
    {
        if ($publicKey === '') {
            return 'BELUM-DITANDATANGANI';
        }

        return implode('-', str_split(strtoupper(substr(hash('sha256', $publicKey.'|'.$signature), 0, 12)), 4));
    }

    public static function notice(): string
    {
        return '© '.self::STATEMENT['tahun'].' '.self::STATEMENT['pemegang_hak_cipta'].' · Pencipta: '.self::STATEMENT['pencipta'].' · Hak cipta dilindungi undang-undang';
    }

    /**
     * Buka segel dengan kunci segel (base64). Null bila kunci salah atau segel rusak.
     *
     * @return array<string, mixed>|null
     */
    public static function open(string $sealKey, string $sealed = self::SEALED): ?array
    {
        $key = base64_decode($sealKey, true);
        $raw = base64_decode($sealed, true);

        if (! $key || ! $raw || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);

        return $plain === false ? null : json_decode($plain, true);
    }

    /** Apakah kunci privat (base64) ini pasangan dari kunci publik yang tertanam. */
    public static function ownsKey(string $signSecret, string $publicKey = self::PUBLIC_KEY): bool
    {
        $secret = base64_decode($signSecret, true);

        if (! $secret || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return false;
        }

        return hash_equals(base64_decode($publicKey, true) ?: '', sodium_crypto_sign_publickey_from_secretkey($secret));
    }

    /**
     * Buat pasangan kunci baru, segel data privat, dan tandatangani pernyataan.
     *
     * @param  array<string, mixed>  $private  data tambahan yang hanya terbaca pemegang kunci segel
     * @return array{public_key: string, sealed: string, signature: string, sign_secret: string, seal_key: string}
     */
    public static function issue(array $private, array $statement = self::STATEMENT): array
    {
        $pair = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($pair);
        $sealKey = sodium_crypto_secretbox_keygen();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $payload = json_encode($statement + $private, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sealed = base64_encode($nonce.sodium_crypto_secretbox($payload, $nonce, $sealKey));
        $signature = sodium_crypto_sign_detached(self::message($statement, $sealed), $secret);

        $result = [
            'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'sealed' => $sealed,
            'signature' => base64_encode($signature),
            'sign_secret' => base64_encode($secret),
            'seal_key' => base64_encode($sealKey),
        ];

        sodium_memzero($secret);
        sodium_memzero($sealKey);
        sodium_memzero($pair);

        return $result;
    }
}
