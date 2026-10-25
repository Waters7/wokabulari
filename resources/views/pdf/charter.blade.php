<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Piagam Apresiasi</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #0b3b38; }
        /* A4 lanskap pada 96 dpi ≈ 1122 × 793 px; ukuran eksplisit karena dompdf tidak meregangkan top+bottom. */
        .frame { position: absolute; top: 20px; left: 20px; width: 1068px; height: 738px; border: 6px solid #007d71; }
        .inner { position: absolute; top: 36px; left: 36px; width: 944px; height: 652px; border: 2px solid #d5e022; padding: 30px 50px; text-align: center; }
        .logos img { height: 52px; margin: 0 14px; }
        .brand { height: 40px; margin-top: 6px; }
        h1 { font-size: 40px; letter-spacing: 6px; margin: 18px 0 4px; color: #007d71; }
        .sub { font-size: 15px; letter-spacing: 2px; color: #5f6600; text-transform: uppercase; }
        .given { margin-top: 22px; font-size: 14px; }
        .name { font-size: 34px; font-weight: bold; margin: 10px 0 4px; border-bottom: 2px solid #d5e022; display: inline-block; padding: 0 30px 6px; }
        .reason { font-size: 14px; line-height: 1.6; margin: 14px auto 0; width: 80%; }
        .points { display: inline-block; margin-top: 12px; padding: 6px 18px; background: #d5e022; color: #0b3b38; font-weight: bold; border-radius: 20px; font-size: 14px; }
        .sign { position: absolute; bottom: 30px; right: 60px; text-align: center; font-size: 13px; }
        .sign .line { margin-top: 60px; border-top: 1px solid #0b3b38; width: 220px; padding-top: 4px; }
        .tagline { position: absolute; bottom: 34px; left: 60px; font-size: 12px; color: #007d71; font-style: italic; }
    </style>
</head>
<body>
    <div class="frame"></div>
    <div class="inner">
        <div class="logos">
            @foreach ($logos as $src)
                <img src="{{ $src }}" alt="Logo">
            @endforeach
        </div>
        @if ($brand)
            <img class="brand" src="{{ $brand }}" alt="Wokabulari">
        @endif

        <h1>PIAGAM APRESIASI</h1>
        <div class="sub">Peserta Terbaik Minggu Ini</div>

        <div class="given">Diberikan kepada</div>
        <div class="name">{{ $best['user']->name }}</div>
        <div>{{ $classroom?->label() }}</div>

        <p class="reason">
            atas semangat belajar, kedisiplinan, dan keaktifan sebagai peserta dengan poin tertinggi pada pekan
            {{ tanggal($best['from'], 'j F') }} – {{ tanggal($best['to'], 'j F Y') }} dalam {{ $program }}.
        </p>
        <div class="points">{{ angka($best['points']) }} poin</div>

        <div class="tagline">Kaya Kosakata, Torang Go Global!</div>
        <div class="sign">
            Manado, {{ tanggal(now(), 'j F Y') }}<br>
            {{ $organizer }}
            <div class="line">Koordinator Program</div>
        </div>
    </div>
</body>
</html>
