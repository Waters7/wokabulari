<?php

namespace App\Services;

use App\Models\Language;
use App\Models\User;
use App\Models\VocabularyWord;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Kartu kosakata harian: 5 kata per hari dari bank bahasa kelas peserta, bergilir otomatis tiap hari
 * (sama untuk semua peserta sebahasa agar bisa dibahas bersama). Bila bank bahasa kelas masih kosong,
 * sementara memakai bank Mandarin.
 */
class DailyVocabulary
{
    public const PER_DAY = 5;

    public const FALLBACK_LANGUAGE = 'zh';

    /** Titik awal hitungan hari agar giliran kata stabil. */
    protected const EPOCH = '2026-01-01';

    /**
     * @return array{language: Language, words: Collection<int, VocabularyWord>, fallback: bool}|null
     */
    public function for(User $user, ?CarbonInterface $date = null): ?array
    {
        $date = CarbonImmutable::parse($date ?? today())->startOfDay();
        $own = $user->activeClassroom()?->language;

        if ($own && ($words = $this->words($own, $date))->isNotEmpty()) {
            return ['language' => $own, 'words' => $words, 'fallback' => false];
        }

        $fallback = Language::query()->where('code', self::FALLBACK_LANGUAGE)->first();
        $words = $fallback ? $this->words($fallback, $date) : collect();

        return $words->isEmpty() ? null : ['language' => $fallback, 'words' => $words, 'fallback' => $own?->isNot($fallback) ?? true];
    }

    /**
     * Lima kata berurutan mulai dari posisi hari ini; kembali ke awal setelah bank habis.
     *
     * @return Collection<int, VocabularyWord>
     */
    public function words(Language $language, CarbonInterface $date): Collection
    {
        $ids = VocabularyWord::query()->where('language_id', $language->id)->active()
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $day = (int) CarbonImmutable::parse(self::EPOCH)->diffInDays(CarbonImmutable::parse($date)->startOfDay(), true);
        $start = ($day * self::PER_DAY) % $ids->count();
        $picked = collect(range(0, min(self::PER_DAY, $ids->count()) - 1))->map(fn (int $i) => $ids[($start + $i) % $ids->count()]);
        $words = VocabularyWord::query()->whereIn('id', $picked)->get()->keyBy('id');

        return $picked->map(fn (int $id) => $words->get($id))->filter()->values();
    }

    /** Kode bahasa untuk suara bawaan browser (Web Speech API). */
    public static function speechLang(Language $language): string
    {
        return match ($language->code) {
            'zh' => 'zh-CN',
            'ja' => 'ja-JP',
            'de' => 'de-DE',
            default => 'en-US',
        };
    }
}
