{{--
    Kop surat + kaki halaman untuk PDF (dompdf). Elemen "fixed" berulang di setiap halaman.
    Butuh: $kop (Letterhead::pdf()), $printedAt, $printedBy. Halaman memberi ruang lewat @page margin
    (atas ≥ 118px, bawah ≥ 46px) dan memakai kelas .kop / .kaki di bawah.
--}}
<style>
    .kop { position: fixed; top: -104px; left: 0; right: 0; height: 92px; }
    .kop table { width: 100%; border-collapse: collapse; margin: 0; }
    .kop td { border: 0; padding: 0; vertical-align: middle; background: none; }
    .kop .garis { border-top: 2.5px solid #00a39a; border-bottom: 1px solid #b5d334; height: 2px; margin-top: 4px; }
    .kaki { position: fixed; bottom: -32px; left: 0; right: 0; height: 18px; font-size: 8.5px; color: #5b7472; border-top: 1px solid #d7ece9; padding-top: 3px; }
</style>
<div class="kop">
    <table>
        <tr>
            <td style="text-align: left;">@if ($kop['logo'])<img src="{{ $kop['logo'] }}" alt="Kemenkes Poltekkes Manado" style="height: 46px;">@endif</td>
            <td style="text-align: right;">@if ($kop['institution'])<img src="{{ $kop['institution'] }}" alt="Politeknik Kesehatan Manado" style="height: 74px;">@endif</td>
        </tr>
    </table>
    <div class="garis"></div>
</div>
{{-- Nomor halaman ditulis Letterhead::output() di pojok kanan kaki. --}}
<div class="kaki">
    Dicetak: {{ tanggal($printedAt, 'l, j F Y') }} pukul {{ jam($printedAt) }} WITA{{ $printedBy ? ' oleh '.$printedBy : '' }}&nbsp;&nbsp;|&nbsp;&nbsp;{{ setting('program.name') }}&nbsp;&nbsp;|&nbsp;&nbsp;Wokabulari v{{ config('wokabulari.version') }}
</div>
