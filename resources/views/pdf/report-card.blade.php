<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor {{ $card['user']->name }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #0b3b38; font-size: 11px; }
        .head { border-bottom: 3px solid #007d71; padding-bottom: 8px; margin-bottom: 12px; }
        .head img { height: 34px; float: right; }
        h1 { font-size: 18px; margin: 0; color: #007d71; }
        .muted { color: #5b7472; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { padding: 5px 6px; border-bottom: 1px solid #d7ece9; text-align: left; }
        th { background: #e6f7f5; font-size: 10px; text-transform: uppercase; }
        .r { text-align: right; }
        .box { border: 1px solid #c5eee9; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; }
        .final { font-size: 30px; font-weight: bold; color: #007d71; }
        .grade { display: inline-block; padding: 2px 10px; background: #d5e022; border-radius: 10px; font-weight: bold; }
        h2 { font-size: 12px; color: #007d71; margin: 14px 0 2px; text-transform: uppercase; letter-spacing: 1px; }
        .grid td { border: 0; padding: 2px 0; }
        .sign { margin-top: 30px; width: 220px; float: right; text-align: center; }
        .sign .line { margin-top: 56px; border-top: 1px solid #0b3b38; padding-top: 3px; }
    </style>
</head>
<body>
    @php
        $scores = $card['scores'];
        $pct = fn ($v) => $v === null ? '–' : angka($v, 1);
        $classroom = $card['classroom'];
    @endphp
    <div class="head">
        @if ($brand)<img src="{{ $brand }}" alt="Wokabulari">@endif
        <h1>Rapor Peserta</h1>
        <div class="muted">{{ $program }} · {{ $organizer }}</div>
    </div>

    <table class="grid">
        <tr><td style="width: 120px;">Nama</td><td><strong>{{ $card['user']->name }}</strong></td><td style="width: 120px;">Kelas</td><td>{{ $classroom->label() }}</td></tr>
        <tr><td>NIS</td><td>{{ $card['user']->username ?? '–' }}</td><td>Angkatan</td><td>{{ $classroom->batch?->name ?? '–' }}</td></tr>
        <tr><td>Periode</td><td>{{ tanggal($card['from']) }} – {{ tanggal($card['to']) }}</td><td>Pengajar</td><td>{{ $classroom->teacher?->name ?? '–' }}</td></tr>
    </table>

    <div class="box" style="margin-top: 12px;">
        <table class="grid">
            <tr>
                <td style="width: 160px;"><div class="muted">Nilai akhir</div><div class="final">{{ $pct($scores['overall'] ?? null) }}</div>@if ($scores['grade'] ?? null)<span class="grade">Predikat {{ $scores['grade'] }}</span>@endif</td>
                @foreach (\App\Services\Reports\ScoreAnalytics::SOURCES as $key => $label)
                    <td class="r"><div class="muted">{{ $label }}</div><div style="font-size: 16px; font-weight: bold;">{{ $pct($scores[$key] ?? null) }}</div></td>
                @endforeach
            </tr>
        </table>
        <div class="muted" style="margin-top: 4px;">
            Kehadiran harian {{ $card['attendance']['rate'] !== null ? angka($card['attendance']['rate'], 1).'%' : '–' }} ·
            kehadiran sesi {{ $card['sessionAttendance']['rate'] !== null ? angka($card['sessionAttendance']['rate'], 1).'%' : '–' }} ({{ $card['sessionAttendance']['count'] }} sesi) ·
            nilai akhir = rata-rata komponen yang sudah ada nilainya.
        </div>
    </div>

    @if ($card['nGain']['pretest']->isNotEmpty() || $card['nGain']['posttest']->isNotEmpty())
        @php($gain = $card['nGain']['row'])
        <h2>Pretest – posttest</h2>
        <table>
            <tr><th>Pretest</th><th>Posttest</th><th>N-Gain</th><th>Kategori</th></tr>
            <tr><td>{{ $pct($gain['pre'] ?? null) }}</td><td>{{ $pct($gain['post'] ?? null) }}</td><td>{{ ($gain['gain'] ?? null) !== null ? angka($gain['gain'], 2) : '–' }}</td><td>{{ $gain['category'] ?? '–' }}</td></tr>
        </table>
        <div class="muted" style="margin-top: 3px;">Jumlah poin semua quiz pretest ({{ $card['nGain']['pretest']->count() }}) dan posttest ({{ $card['nGain']['posttest']->count() }}).@if ($gain['pending']) Nilai sementara: masih ada esai yang belum selesai dinilai.@endif</div>
    @endif

    <h2>Quiz & ujian</h2>
    <table>
        <tr><th>Quiz</th><th>Jenis</th><th class="r">Poin</th><th class="r">Nilai</th></tr>
        @forelse ($card['quizzes'] as $item)
            <tr>
                <td>{{ $item['quiz']->title }}</td>
                <td>{{ $item['quiz']->type->label() }}</td>
                <td class="r">{{ $item['attempt'] ? poin($item['attempt']->score).'/'.poin($item['attempt']->max_score) : '–' }}</td>
                <td class="r">{{ ! $item['attempt'] ? 'belum' : ($item['attempt']->percentage === null ? 'menunggu dinilai' : angka($item['attempt']->percentage, 1)) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Belum ada quiz.</td></tr>
        @endforelse
    </table>

    <h2>Tugas</h2>
    <table>
        <tr><th>Tugas</th><th>Tenggat</th><th class="r">Nilai</th></tr>
        @forelse ($card['assignments'] as $item)
            <tr>
                <td>{{ $item['assignment']->title }}</td>
                <td>{{ $item['assignment']->due_at ? tanggal($item['assignment']->due_at) : '–' }}</td>
                <td class="r">{{ ! $item['submission'] ? 'belum mengumpulkan' : ($item['submission']->percentage() === null ? 'menunggu dinilai' : angka($item['submission']->percentage(), 1).($item['submission']->is_late ? ' (terlambat)' : '')) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Belum ada tugas.</td></tr>
        @endforelse
    </table>

    <h2>Penilaian periodik</h2>
    <table>
        <tr><th>Periode</th><th>Nilai per aspek</th><th class="r">Rata-rata</th><th class="r">Predikat</th></tr>
        @forelse ($card['periodic'] as $assessment)
            <tr>
                <td>{{ $assessment->periodLabel() }}</td>
                <td>{{ $assessment->orderedScores()->map(fn ($s) => $s->aspect->name.' '.angka($s->score))->implode(', ') }}</td>
                <td class="r">{{ angka($assessment->average, 1) }}</td>
                <td class="r">{{ $assessment->grade }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Belum ada penilaian periodik yang terbit.</td></tr>
        @endforelse
    </table>

    <div class="sign">
        Manado, {{ tanggal(now()) }}<br>Pengajar
        <div class="line">{{ $classroom->teacher?->name ?? '' }}</div>
    </div>
</body>
</html>
