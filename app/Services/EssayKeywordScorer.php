<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Saran nilai esai berdasarkan kata kunci (makro penilaian). Contoh soal "Sebutkan 3 olahraga":
 * kata kunci "跑步/paobu, 游泳/youyong, 篮球/lanqiu", perlu 3 → tiap kata kunci yang muncul menambah nilai.
 *
 * Huruf Mandarin dibandingkan apa adanya; huruf Latin diratakan (tanpa tanda nada, spasi, dan tanda baca),
 * sehingga "pǎobù", "Paobu", dan "pao bu" dianggap sama. Hasilnya hanya saran: pengajar tetap memeriksa.
 */
class EssayKeywordScorer
{
    public static function normalize(string $text): string
    {
        return (string) preg_replace_callback('/[^\p{Han}]+/u',
            fn ($m) => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($m[0]))),
            $text);
    }

    /**
     * @return list<list<string>> kata kunci; tiap kata kunci boleh punya alternatif dipisah "/"
     */
    public static function parse(string $keywords): array
    {
        return collect(preg_split('/[,;\n]+/u', $keywords))
            ->map(fn ($group) => collect(explode('/', $group))->map(fn ($k) => self::normalize(trim($k)))->filter()->unique()->values()->all())
            ->filter()
            ->values()
            ->all();
    }

    /** Jumlah kata kunci yang muncul di jawaban. */
    public function matches(string $answer, array $keywords): int
    {
        $text = self::normalize($answer);

        return collect($keywords)->filter(fn (array $alternatives) => collect($alternatives)->contains(fn ($k) => str_contains($text, $k)))->count();
    }

    /** Nilai = poin maksimal × (kata kunci yang muncul ÷ jumlah yang dibutuhkan), paling besar poin maksimal. */
    public function score(string $answer, array $keywords, int $needed, float $maxPoints): float
    {
        if ($keywords === [] || $needed < 1) {
            return 0.0;
        }

        return round($maxPoints * min($this->matches($answer, $keywords), $needed) / $needed, 2);
    }
}
