{{-- Teks dengan bacaan (pinyin/romaji). display: ruby | hidden | tap --}}
@props(['text', 'reading' => null, 'display' => 'ruby', 'as' => 'span'])

@php
    $display = $display instanceof \App\Enums\ReadingDisplay ? $display->value : $display;
    $isCjk = (bool) preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', (string) $text);
@endphp

<{{ $as }} {{ $attributes->class(['text-cjk' => $isCjk]) }} @if ($isCjk) lang="{{ preg_match('/[\x{3040}-\x{30FF}]/u', (string) $text) ? 'ja' : 'zh' }}" @endif>
    @if (blank($reading) || $display === 'hidden')
        {{ $text }}
    @elseif ($display === 'ruby')
        <ruby>{{ $text }}<rp>(</rp><rt>{{ $reading }}</rt><rp>)</rp></ruby>
    @else
        <span x-data="{ show: false }" class="inline">
            <button type="button" x-on:click.stop="show = !show" class="underline decoration-dotted decoration-woka underline-offset-4" :aria-expanded="show">{{ $text }}</button>
            <span x-show="show" x-cloak x-transition class="ms-1 text-[0.8em] text-woka">({{ $reading }})</span>
        </span>
    @endif
</{{ $as }}>
